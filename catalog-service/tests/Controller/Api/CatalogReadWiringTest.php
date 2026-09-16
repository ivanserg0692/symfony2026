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
use App\Search\Product\Port\Output\CatalogReadInterface;
use App\Search\Product\Port\Output\CatalogSectionReadInterface;
use Elastic\Elasticsearch\ClientBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogReadWiringTest extends KernelTestCase
{
    /** @dataProvider backends */
    public function testConfigurationSelectsBackends(
        string $catalogModel,
        string $sectionModel,
        string $expectedCatalogClass,
        string $expectedSectionClass,
    ): void
    {
        $this->withModels($catalogModel, $sectionModel, function () use ($expectedCatalogClass, $expectedSectionClass): void {
            self::bootKernel();
            self::assertInstanceOf($expectedCatalogClass, static::getContainer()->get(CatalogReadInterface::class));
            self::assertInstanceOf(
                CatalogReadService::class,
                static::getContainer()->get(CatalogReadInputInterface::class),
            );
            self::assertInstanceOf(
                $expectedSectionClass,
                static::getContainer()->get(CatalogSectionReadInterface::class),
            );
            self::assertInstanceOf(
                CatalogSectionReadService::class,
                static::getContainer()->get(CatalogSectionReadInputInterface::class),
            );
        });
    }

    public static function backends(): iterable
    {
        yield ["doctrine", "doctrine", DoctrineCatalogReader::class, DoctrineCatalogSectionReader::class];
        yield ["doctrine", "elasticsearch", DoctrineCatalogReader::class, ElasticsearchCatalogSectionReader::class];
        yield ["elasticsearch", "doctrine", ElasticsearchCatalogReader::class, DoctrineCatalogSectionReader::class];
        yield ["elasticsearch", "elasticsearch", ElasticsearchCatalogReader::class, ElasticsearchCatalogSectionReader::class];
    }

    public function testSectionsDefaultToDoctrineWhenSettingIsAbsent(): void
    {
        $this->withModels("elasticsearch", null, function (): void {
            self::bootKernel();
            self::assertInstanceOf(ElasticsearchCatalogReader::class, static::getContainer()->get(CatalogReadInterface::class));
            self::assertInstanceOf(DoctrineCatalogSectionReader::class, static::getContainer()->get(CatalogSectionReadInterface::class));
        });
    }

    public function testElasticsearchReadsWithoutOpeningDatabaseConnection(): void
    {
        $this->withModels("elasticsearch", "doctrine", function (): void {
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
        $this->withModels('doctrine', 'elasticsearch', function (): void {
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

    private function withModels(string $catalogModel, ?string $sectionModel, \Closure $test): void
    {
        $previous = [];
        foreach (["CATALOG_READ_MODEL" => $catalogModel, "CATALOG_SECTION_READ_MODEL" => $sectionModel] as $name => $value) {
            $previous[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            if ($value === null) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
            } else {
                $_ENV[$name] = $_SERVER[$name] = $value;
                putenv($name."=".$value);
            }
        }
        try {
            $test();
        } finally {
            self::ensureKernelShutdown();
            foreach ($previous as $name => [$env, $server, $process]) {
                unset($_ENV[$name], $_SERVER[$name]);
                if ($env !== null) {
                    $_ENV[$name] = $env;
                }
                if ($server !== null) {
                    $_SERVER[$name] = $server;
                }
                putenv($process === false ? $name : $name."=".$process);
            }
        }
    }
}
