<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSectionAggregationQueryBuilder;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchGateway;
use App\Search\Product\Infrastructure\Elasticsearch\ElasticsearchCatalogSectionReader;
use Elastic\Elasticsearch\ClientBuilder;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class ElasticsearchCatalogSectionReaderTest extends TestCase
{
    public function testListReadsEveryCompositePageAndSortsUniqueActiveSections(): void
    {
        $reader = $this->reader([
            static function (RequestInterface $request): array {
                self::assertSame('/products/_pit', $request->getUri()->getPath());
                return ['id' => 'pit-1'];
            },
            function (RequestInterface $request): array {
                self::assertSame('/_search', $request->getUri()->getPath());
                $body = json_decode((string) $request->getBody(), true);
                self::assertSame(0, $body['size']);
                self::assertArrayNotHasKey('query', $body);
                self::assertSame('pit-1', $body['pit']['id']);
                self::assertSame(['term' => ['sections.active' => true]], $body['aggs']['sections']['aggs']['active']['filter']);
                self::assertSame('sections', $body['aggs']['sections']['nested']['path']);
                self::assertSame('sections.id', $body['aggs']['sections']['aggs']['active']['aggs']['by_id']['composite']['sources'][0]['id']['terms']['field']);
                self::assertArrayNotHasKey('after', $body['aggs']['sections']['aggs']['active']['aggs']['by_id']['composite']);
                return $this->activeResult([
                    $this->bucket(2, 10),
                    $this->bucket(7, 50),
                ], ['id' => 7], 'pit-2');
            },
            function (RequestInterface $request): array {
                $body = json_decode((string) $request->getBody(), true);
                self::assertSame('pit-2', $body['pit']['id']);
                self::assertSame(['id' => 7], $body['aggs']['sections']['aggs']['active']['aggs']['by_id']['composite']['after']);
                return $this->activeResult([$this->bucket(9, 50)], ['id' => 9], 'pit-3');
            },
            function (RequestInterface $request): array {
                $body = json_decode((string) $request->getBody(), true);
                self::assertSame(['id' => 9], $body['aggs']['sections']['aggs']['active']['aggs']['by_id']['composite']['after']);
                return $this->activeResult([], null, 'pit-4');
            },
            static function (RequestInterface $request): array {
                self::assertSame('DELETE', $request->getMethod());
                self::assertSame(['id' => 'pit-4'], json_decode((string) $request->getBody(), true));
                return ['succeeded' => true];
            },
        ]);

        self::assertSame([7, 9, 2], array_map(static fn($section) => $section->id, $reader->findActive()));
    }

    public function testItemIncludesInactiveSectionAndMissingSectionReturnsNull(): void
    {
        $reader = $this->reader([
            function (RequestInterface $request): array {
                self::assertSame('/products/_search', $request->getUri()->getPath());
                $body = json_decode((string) $request->getBody(), true);
                self::assertSame(['nested' => [
                    'path' => 'sections', 'query' => ['term' => ['sections.id' => 42]], 'score_mode' => 'none',
                ]], $body['query']);
                self::assertSame(['term' => ['sections.id' => 42]], $body['aggs']['sections']['aggs']['matching']['filter']);
                self::assertArrayNotHasKey('active', $body['aggs']['sections']['aggs']);
                return ['aggregations' => ['sections' => ['matching' => ['section' => [
                    'hits' => ['hits' => [['_source' => $this->section(42, false)]]],
                ]]]]];
            },
            static fn(): array => ['aggregations' => ['sections' => ['matching' => ['section' => [
                'hits' => ['hits' => []],
            ]]]]],
        ]);

        self::assertFalse($reader->findById(42)->active);
        self::assertNull($reader->findById(99));
    }

    public function testTimedOutAggregationIsRejectedAndPitClosed(): void
    {
        $reader = $this->reader([
            static fn(): array => ['id' => 'pit'],
            static fn(): array => ['timed_out' => true],
            static function (RequestInterface $request): array {
                self::assertSame(['id' => 'pit'], json_decode((string) $request->getBody(), true));
                return ['succeeded' => true];
            },
        ]);

        $this->expectExceptionMessage('incomplete catalog section');
        $reader->findActive();
    }

    public function testMissingBucketsAreNotTreatedAsAnEmptyPage(): void
    {
        $reader = $this->reader([
            static fn(): array => ['id' => 'pit'],
            static fn(): array => ['aggregations' => ['sections' => ['active' => ['by_id' => []]]]],
            static fn(): array => ['succeeded' => true],
        ]);

        $this->expectExceptionMessage('missing buckets');
        $reader->findActive();
    }

    public function testMissingAggregationIsNotReportedAsMissingSection(): void
    {
        $reader = $this->reader([static fn(): array => ['hits' => ['hits' => []]]]);

        $this->expectExceptionMessage('missing hits');
        $reader->findById(42);
    }

    /** @param list<callable(RequestInterface): array> $steps */
    private function reader(array $steps): ElasticsearchCatalogSectionReader
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::exactly(count($steps)))->method('sendRequest')
            ->willReturnCallback(static function (RequestInterface $request) use (&$steps): Response {
                $step = array_shift($steps);
                return new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'],
                    json_encode($step($request), JSON_THROW_ON_ERROR));
            });
        $client = ClientBuilder::create()->setHosts(['http://elasticsearch:9200'])->setHttpClient($http)->build();

        return new ElasticsearchCatalogSectionReader(
            new CatalogSearchGateway($client, 'products'),
            new CatalogSectionAggregationQueryBuilder(),
        );
    }

    private function activeResult(array $buckets, ?array $afterKey, string $pit): array
    {
        $byId = ['buckets' => $buckets];
        if ($afterKey !== null) {
            $byId['after_key'] = $afterKey;
        }

        return ['pit_id' => $pit, 'aggregations' => ['sections' => ['active' => ['by_id' => $byId]]]];
    }

    private function bucket(int $id, int $sort): array
    {
        return ['key' => ['id' => $id], 'section' => ['hits' => ['hits' => [
            ['_source' => $this->section($id, true, $sort)],
        ]]]];
    }

    private function section(int $id, bool $active, int $sort = 100): array
    {
        return [
            'id' => $id, 'name' => 'Section '.$id, 'slug' => 'section-'.$id,
            'active' => $active, 'description' => null, 'picture_id' => null,
            'level' => 0, 'sort' => $sort, 'parent_id' => null,
        ];
    }
}
