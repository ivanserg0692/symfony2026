<?php

namespace App\Tests\Controller\Api;

use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Tests\Support\CatalogMediaFactory;
use App\Search\Product\Application\ProductSearchDocumentBuilder;
use App\Search\Product\Application\CatalogReadService;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Doctrine\DoctrineCatalogReader;
use App\Search\Product\Infrastructure\Elasticsearch\ElasticsearchCatalogReader;
use App\Search\Product\Port\Input\CatalogReadInputInterface;
use App\Search\Product\Port\Output\CatalogReadInterface;
use Elastic\Elasticsearch\ClientBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogReadWiringTest extends KernelTestCase
{
    /** @dataProvider backends */
    public function testConfigurationSelectsBackend(string $model, string $expectedClass): void
    {
        $this->withModel($model, function () use ($expectedClass): void {
            self::bootKernel();
            self::assertInstanceOf($expectedClass, static::getContainer()->get(CatalogReadInterface::class));
            self::assertInstanceOf(
                CatalogReadService::class,
                static::getContainer()->get(CatalogReadInputInterface::class),
            );
        });
    }

    public static function backends(): iterable
    {
        yield ["doctrine", DoctrineCatalogReader::class];
        yield ["elasticsearch", ElasticsearchCatalogReader::class];
    }

    public function testElasticsearchReadsWithoutOpeningDatabaseConnection(): void
    {
        $this->withModel("elasticsearch", function (): void {
            self::bootKernel();
            $container = static::getContainer();
            $http = $this->createMock(ClientInterface::class);
            $http->expects(self::exactly(2))->method("sendRequest")->willReturnOnConsecutiveCalls(
                new Response(200, ["X-Elastic-Product" => "Elasticsearch", "Content-Type" => "application/json"], '{"hits":{"hits":[],"total":{"value":0,"relation":"eq"}}}'),
                new Response(200, ["X-Elastic-Product" => "Elasticsearch", "Content-Type" => "application/json"], '{"docs":[{"found":false}]}'),
            );
            $container->set("app.catalog_read.elasticsearch_client", ClientBuilder::create()
                ->setHosts(["http://elasticsearch:9200"])->setHttpClient($http)->build());
            $connection = $container->get("doctrine.dbal.default_connection");
            self::assertFalse($connection->isConnected());
            $service = $container->get(CatalogReadInputInterface::class);
            $page = $service->list(new CatalogListCriteria(null, null, 1, 20, false));
            self::assertSame([], $page->items);
            self::assertSame(["page" => 1, "limit" => 20, "total" => 0], $page->pagination);
            self::assertNull($service->item(42));
            self::assertFalse($connection->isConnected());
        });
    }

    public function testPopulatedElasticsearchPicturesNeverOpenDatabaseConnection(): void
    {
        $this->withModel('elasticsearch', function (): void {
            self::bootKernel();
            $container = static::getContainer();
            $sources = [];
            for ($id = 1; $id <= 30; ++$id) {
                $element = (new CatalogElements())->setName('Chair')
                    ->setPicture(CatalogMediaFactory::create('chair', 'Product '.$id))
                    ->addSection((new CatalogSections())->setId(7)
                        ->setPicture(CatalogMediaFactory::create('chair', 'Section')));
                (new \ReflectionProperty($element, 'id'))->setValue($element, $id);
                $element->getProduct()->setId(100 + $id);
                $sources[] = (new ProductSearchDocumentBuilder())->build($element)->toArray();
            }
            $responses = [
                ['hits' => ['hits' => array_map(static fn (array $source) => ['_source' => $source], $sources), 'total' => ['value' => 30, 'relation' => 'eq']]],
                ['docs' => [['found' => true, '_source' => $sources[0]]]],
            ];
            $http = $this->createMock(ClientInterface::class);
            $http->expects(self::exactly(2))->method('sendRequest')->willReturnCallback(
                static function () use (&$responses): Response {
                    return new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'],
                        json_encode(array_shift($responses), JSON_THROW_ON_ERROR));
                },
            );
            $container->set('app.catalog_read.elasticsearch_client', ClientBuilder::create()
                ->setHosts(['http://elasticsearch:9200'])->setHttpClient($http)->build());
            $connection = $container->get('doctrine.dbal.default_connection');
            self::assertFalse($connection->isConnected());
            $reader = $container->get(CatalogReadInterface::class);
            $page = $reader->findPage(new CatalogListCriteria(null, null, 1, 30, false));
            self::assertCount(30, $page->items);
            self::assertSame('Product 1', $page->items[0]->picture->alt);
            self::assertSame('Product 30', $page->items[29]->picture->alt);
            self::assertSame($page->items[0]->picture->fullUrl, $page->items[29]->picture->fullUrl);
            $item = $reader->findById(1);
            self::assertSame('Section', $item->sections[0]->picture->alt);
            self::assertSame($item->picture->fullUrl, $item->sections[0]->picture->fullUrl);
            self::assertFalse($connection->isConnected());
        });
    }

    private function withModel(string $model, \Closure $test): void
    {
        $env = $_ENV["CATALOG_READ_MODEL"] ?? null;
        $server = $_SERVER["CATALOG_READ_MODEL"] ?? null;
        $process = getenv("CATALOG_READ_MODEL");
        $_ENV["CATALOG_READ_MODEL"] = $_SERVER["CATALOG_READ_MODEL"] = $model;
        putenv("CATALOG_READ_MODEL=".$model);
        try {
            $test();
        } finally {
            self::ensureKernelShutdown();
            unset($_ENV["CATALOG_READ_MODEL"], $_SERVER["CATALOG_READ_MODEL"]);
            if ($env !== null) {
                $_ENV["CATALOG_READ_MODEL"] = $env;
            }
            if ($server !== null) {
                $_SERVER["CATALOG_READ_MODEL"] = $server;
            }
            putenv($process === false ? "CATALOG_READ_MODEL" : "CATALOG_READ_MODEL=".$process);
        }
    }
}
