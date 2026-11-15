<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Elasticsearch;

use App\Media\Infrastructure\CatalogPictureResponseFactory;
use App\Search\Product\Infrastructure\CatalogResponseFactory;
use App\Search\Product\Application\Dto\Document\ProductSearchPicture;
use App\Tests\Support\CatalogMediaFactory;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Elasticsearch\ElasticsearchCatalogReader;
use App\Search\Product\Infrastructure\Elasticsearch\ProductSearchRepository;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchGateway;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchQueryBuilder;
use Elastic\Elasticsearch\ClientBuilder;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class ElasticsearchCatalogReaderTest extends TestCase
{
    /** @dataProvider paginationCases */
    public function testListQueryAndPagination(bool $lookAhead, int $hitCount, bool $hasNext): void
    {
        $reader = $this->reader([function (RequestInterface $request) use ($lookAhead, $hitCount): array {
            self::assertSame("/products/_search", $request->getUri()->getPath());
            $body = json_decode((string) $request->getBody(), true);
            self::assertSame([
                ["terms" => ["section_ids" => [7]]],
                ["term" => ["active" => false]],
            ], $body["query"]["bool"]["filter"]);
            self::assertSame([["sort" => ["order" => "desc", "missing" => "_first"]], ["id" => "asc"]], $body["sort"]);
            self::assertSame(2, $body["from"]);
            self::assertSame($lookAhead ? 3 : 2, $body["size"]);
            self::assertSame(!$lookAhead, $body["track_total_hits"]);
            self::assertStringContainsString("allow_partial_search_results=false", $request->getUri()->getQuery());

            return $this->result(array_map($this->hit(...), range(1, $hitCount)));
        }]);
        $page = $reader->findPage(new CatalogListCriteria(7, false, 2, 2, $lookAhead));
        self::assertSame([1, 2], array_map(static fn($item) => $item->id, $page->items));
        self::assertSame($lookAhead ? null : 15001, $page->total);
        self::assertSame($hasNext, $page->hasNextPage);
    }

    public static function paginationCases(): iterable
    {
        yield [false, 2, false];
        yield [true, 3, true];
        yield [true, 2, false];
    }

    public function testEmptyUnfilteredPage(): void
    {
        $reader = $this->reader([function (RequestInterface $request): array {
            $body = json_decode((string) $request->getBody());
            self::assertEquals(new \stdClass(), $body->query->match_all);

            return $this->result([]);
        }]);
        $page = $reader->findPage(new CatalogListCriteria(null, null, 99, 20, false));
        self::assertSame([], $page->items);
        self::assertSame(15001, $page->total);
    }

    /** @dataProvider deepModes */
    public function testDeepPageUsesPitAndSearchAfter(bool $lookAhead): void
    {
        $steps = [static function (RequestInterface $request): array {
            self::assertSame("/products/_pit", $request->getUri()->getPath());
            return ["id" => "pit-0"];
        }];
        for ($batch = 0; $batch < 10; ++$batch) {
            $steps[] = function (RequestInterface $request) use ($batch, $lookAhead): array {
                $body = json_decode((string) $request->getBody(), true);
                self::assertSame("/_search", $request->getUri()->getPath());
                self::assertArrayNotHasKey("from", $body);
                self::assertSame("pit-".$batch, $body["pit"]["id"]);
                self::assertSame(1000, $body["size"]);
                self::assertFalse($body["_source"]);
                self::assertSame(!$lookAhead && $batch === 0, $body["track_total_hits"]);
                if ($batch > 0) {
                    self::assertSame([100, $batch * 1000, $batch * 1000], $body["search_after"]);
                }
                $lastId = ($batch + 1) * 1000;
                return $this->result(array_fill(0, 1000, ["sort" => [100, $lastId, $lastId]]))
                    + ["pit_id" => "pit-".($batch + 1)];
            };
        }
        $steps[] = function (RequestInterface $request) use ($lookAhead): array {
            $body = json_decode((string) $request->getBody(), true);
            self::assertSame([100, 10000, 10000], $body["search_after"]);
            self::assertSame($lookAhead ? 101 : 100, $body["size"]);
            self::assertTrue($body["_source"]);
            self::assertFalse($body["track_total_hits"]);
            return $this->result([$this->hit(10001)]) + ["pit_id" => "pit-final"];
        };
        $steps[] = static function (RequestInterface $request): array {
            self::assertSame("DELETE", $request->getMethod());
            self::assertSame(["id" => "pit-final"], json_decode((string) $request->getBody(), true));
            return ["succeeded" => true, "num_freed" => 1];
        };
        $page = $this->reader($steps)->findPage(new CatalogListCriteria(null, true, 101, 100, $lookAhead));
        self::assertSame(10001, $page->items[0]->id);
        self::assertSame($lookAhead ? null : 15001, $page->total);
        self::assertFalse($page->hasNextPage);
    }

    public static function deepModes(): iterable
    {
        yield [true];
        yield [false];
    }

    public function testDeepPageBeyondEndClosesPitAndKeepsTotal(): void
    {
        $reader = $this->reader([
            static fn() => ["id" => "pit"],
            fn() => $this->result([]),
            static fn() => ["succeeded" => true],
        ]);
        $page = $reader->findPage(new CatalogListCriteria(null, null, 1000, 100, false));
        self::assertSame([], $page->items);
        self::assertSame(15001, $page->total);
    }

    public function testPitIsClosedOnFailure(): void
    {
        $reader = $this->reader([
            static fn() => ["id" => "pit"],
            static fn() => ["timed_out" => true, "pit_id" => "latest-pit"],
            static function (RequestInterface $request): array {
                self::assertSame("DELETE", $request->getMethod());
                self::assertSame(["id" => "latest-pit"], json_decode((string) $request->getBody(), true));
                return ["succeeded" => true];
            },
        ]);
        $this->expectExceptionMessage("incomplete catalog search");
        $reader->findPage(new CatalogListCriteria(null, null, 1000, 100, true));
    }

    public function testApproximateTotalIsRejected(): void
    {
        $reader = $this->reader([static fn() => ["hits" => [
            "hits" => [], "total" => ["value" => 10000, "relation" => "gte"],
        ]]]);
        $this->expectExceptionMessage("exact catalog total");
        $reader->findPage(new CatalogListCriteria(null, null, 1, 20, false));
    }

    public function testItemUsesCatalogElementIdAndMissingItemReturnsNull(): void
    {
        $reader = $this->reader([
            function (RequestInterface $request): array {
                self::assertSame("/products/_mget", $request->getUri()->getPath());
                self::assertSame(["ids" => ["42"]], json_decode((string) $request->getBody(), true));
                return ["docs" => [["found" => true, "_source" => $this->hit(42)["_source"]]]];
            },
            static fn() => ["docs" => [["found" => false]]],
        ]);
        self::assertSame(42, $reader->findById(42)->id);
        self::assertNull($reader->findById(99));
    }

    public function testItemIndexFailureIsNotNotFound(): void
    {
        $reader = $this->reader([static fn() => ["docs" => [["error" => ["type" => "index_not_found_exception"]]]]]);
        $this->expectException(\RuntimeException::class);
        $reader->findById(42);
    }

    public function testPicturesComeFromDocumentsAndExcludeLookAheadHit(): void
    {
        $picture = ProductSearchPicture::fromEntity(
            CatalogMediaFactory::create('chair', 'Product alt'),
        )->toArray();
        $hit = $this->hit(1);
        $hit['_source']['picture'] = $picture;
        $hit['_source']['sections'] = [[
            'id' => 1, 'name' => 'Furniture', 'slug' => 'furniture', 'active' => true,
            'description' => null, 'picture' => array_replace($picture, ['alt' => 'Section alt']),
            'level' => 1, 'sort' => 1, 'parent_id' => null,
        ]];
        // The extra hit has no source; mapping it would fail.
        $reader = $this->reader([fn () => $this->result([$hit, []])]);
        $page = $reader->findPage(new CatalogListCriteria(null, null, 1, 1, true));
        self::assertCount(1, $page->items);
        self::assertSame('Product alt', $page->items[0]->picture->alt);
        self::assertSame('Section alt', $page->items[0]->sections[0]->picture->alt);
        self::assertSame($page->items[0]->picture->fullUrl, $page->items[0]->sections[0]->picture->fullUrl);
        self::assertStringStartsWith('http://localhost:8001/api/v1/catalog/media/', $page->items[0]->picture->fullUrl);
        self::assertTrue($page->hasNextPage);
    }

    /** @param list<callable> $steps */
    private function reader(array $steps): ElasticsearchCatalogReader
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::exactly(count($steps)))->method("sendRequest")
            ->willReturnCallback(static function (RequestInterface $request) use (&$steps): Response {
                $step = array_shift($steps);
                return new Response(200, ["X-Elastic-Product" => "Elasticsearch", "Content-Type" => "application/json"],
                    json_encode($step($request), JSON_THROW_ON_ERROR));
            });
        $client = ClientBuilder::create()->setHosts(["http://elasticsearch:9200"])->setHttpClient($http)->build();

        return new ElasticsearchCatalogReader(
            new ProductSearchRepository(
                new CatalogSearchGateway($client, "products"),
                new CatalogSearchQueryBuilder(10000),
            ),
            new CatalogResponseFactory(new CatalogPictureResponseFactory("http://localhost:8001/api/v1/catalog/media")),
        );
    }

    private function result(array $hits): array
    {
        return ["hits" => ["hits" => $hits, "total" => ["value" => 15001, "relation" => "eq"]]];
    }

    private function hit(int $id): array
    {
        return ["_source" => [
            "id" => $id, "product_id" => $id + 100, "name" => "Product", "active" => false, "description" => null,
            "slug" => "product", "picture" => null, "sort" => 100,
            "prices" => [], "sections" => [], "total_stock" => 0,
        ]];
    }
}
