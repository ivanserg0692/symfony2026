<?php

namespace App\Tests\Controller\Api;

use App\Search\Product\Application\CatalogReadService;
use App\Search\Product\Application\CatalogSectionReadService;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Doctrine\DoctrineCatalogReader;
use App\Search\Product\Infrastructure\Doctrine\DoctrineCatalogSectionReader;
use App\Search\Product\Infrastructure\Elasticsearch\ElasticsearchCatalogReader;
use App\Search\Product\Infrastructure\Elasticsearch\ElasticsearchCatalogSectionReader;
use App\Search\Product\Port\Input\CatalogReadInputInterface;
use App\Search\Product\Port\Input\CatalogSectionReadInputInterface;
use App\Search\Product\Port\Input\CatalogReadInputInterface;
use App\Search\Product\Port\Output\CatalogReadInterface;
use App\Search\Product\Port\Output\CatalogSectionReadInterface;
use Elastic\Elasticsearch\ClientBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogReadWiringTest extends KernelTestCase
{
    /** @dataProvider backends */
    public function testConfigurationSelectsBackend(string $model, string $expectedClass): void
    {
        $this->withModel($model, function () use ($model, $expectedClass): void {
            self::bootKernel();
            self::assertInstanceOf($expectedClass, static::getContainer()->get(CatalogReadInterface::class));
            self::assertInstanceOf(
                CatalogReadService::class,
                static::getContainer()->get(CatalogReadInputInterface::class),
            );
            self::assertInstanceOf(
                $model === 'doctrine' ? DoctrineCatalogSectionReader::class : ElasticsearchCatalogSectionReader::class,
                static::getContainer()->get(CatalogSectionReadInterface::class),
            );
            self::assertInstanceOf(
                CatalogSectionReadService::class,
                static::getContainer()->get(CatalogSectionReadInputInterface::class),
            );
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

    public function testElasticsearchSectionReadDoesNotOpenDatabaseConnection(): void
    {
        $this->withModel('elasticsearch', function (): void {
            self::bootKernel();
            $container = static::getContainer();
            $http = $this->createMock(ClientInterface::class);
            $http->expects(self::exactly(4))->method('sendRequest')->willReturnOnConsecutiveCalls(
                new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], '{"id":"pit"}'),
                new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], '{"aggregations":{"sections":{"active":{"by_id":{"buckets":[]}}}}}'),
                new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], '{"succeeded":true}'),
                new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], '{"aggregations":{"sections":{"matching":{"section":{"hits":{"hits":[]}}}}}}'),
            );
            $container->set('app.catalog_read.elasticsearch_client', ClientBuilder::create()
                ->setHosts(['http://elasticsearch:9200'])->setHttpClient($http)->build());
            $connection = $container->get('doctrine.dbal.default_connection');
            self::assertFalse($connection->isConnected());
            $service = $container->get(CatalogSectionReadInputInterface::class);
            self::assertSame([], $service->list());
            self::assertNull($service->item(42));
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
