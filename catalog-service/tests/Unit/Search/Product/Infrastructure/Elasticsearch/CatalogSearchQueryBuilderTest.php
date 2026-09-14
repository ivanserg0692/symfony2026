<?php

namespace AppTests\Unit\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchQueryBuilder;
use PHPUnit\Framework\TestCase;

final class CatalogSearchQueryBuilderTest extends TestCase
{
    private CatalogSearchQueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CatalogSearchQueryBuilder(10000);
    }

    public function testBuildsFullTextQueryWithNameBoostAndKeepsExistingSort(): void
    {
        $body = $this->builder->build($this->criteria(query: "winter jacket"));

        self::assertSame([
            "bool" => ["must" => [["multi_match" => [
                "query" => "winter jacket",
                "fields" => ["name^3", "description"],
            ]]]],
        ], $body["query"]);
        self::assertSame([
            ["sort" => ["order" => "desc", "missing" => "_first"]],
            ["id" => "asc"],
        ], $body["sort"]);
    }

    public function testFiltersByActiveState(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["term" => ["active" => false]]]]],
            $this->builder->build($this->criteria(active: false))["query"],
        );
    }

    public function testFiltersByOneDirectSection(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["terms" => ["section_ids" => [12]]]]]],
            $this->builder->build($this->criteria(sectionIds: [12]))["query"],
        );
    }

    public function testFiltersBySeveralDirectSectionsWithOrSemantics(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["terms" => ["section_ids" => [12, 24]]]]]],
            $this->builder->build($this->criteria(sectionIds: [12, 24]))["query"],
        );
    }

    public function testFiltersByInclusivePriceRange(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["nested" => [
                "path" => "prices",
                "query" => ["bool" => ["filter" => [[
                    "range" => ["prices.amount" => ["gte" => 1000, "lte" => 5000]],
                ]]]],
            ]]]]],
            $this->builder->build($this->criteria(priceFrom: 1000, priceTo: 5000))["query"],
        );
    }

    public function testCombinesPriceTypesAndRangeInOneNestedPriceQuery(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["nested" => [
                "path" => "prices",
                "query" => ["bool" => ["filter" => [
                    ["terms" => ["prices.type_code" => ["retail", "promo"]]],
                    ["range" => ["prices.amount" => ["gte" => 1000, "lte" => 5000]]],
                ]]],
            ]]]]],
            $this->builder->build($this->criteria(
                priceFrom: 1000,
                priceTo: 5000,
                priceTypeCodes: ["retail", "promo"],
            ))["query"],
        );
    }

    public function testFiltersProductsInStock(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["range" => ["total_stock" => ["gt" => 0]]]]]],
            $this->builder->build($this->criteria(inStock: true))["query"],
        );
    }

    public function testFiltersProductsOutOfStock(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["range" => ["total_stock" => ["lte" => 0]]]]]],
            $this->builder->build($this->criteria(inStock: false))["query"],
        );
    }

    public function testCombinesSearchAndAllFilters(): void
    {
        $query = $this->builder->build($this->criteria(
            sectionId: 7,
            active: true,
            query: "boots",
            sectionIds: [8],
            priceFrom: 500,
            priceTo: 3000,
            priceTypeCodes: ["retail"],
            inStock: true,
        ))["query"];

        self::assertSame([["multi_match" => [
            "query" => "boots",
            "fields" => ["name^3", "description"],
        ]]], $query["bool"]["must"]);
        self::assertSame([
            ["terms" => ["section_ids" => [8, 7]]],
            ["term" => ["active" => true]],
            ["nested" => [
                "path" => "prices",
                "query" => ["bool" => ["filter" => [
                    ["terms" => ["prices.type_code" => ["retail"]]],
                    ["range" => ["prices.amount" => ["gte" => 500, "lte" => 3000]]],
                ]]],
            ]],
            ["range" => ["total_stock" => ["gt" => 0]]],
        ], $query["bool"]["filter"]);
    }

    public function testNoNewParametersKeepsMatchAllQueryAndPagination(): void
    {
        $body = $this->builder->buildOffsetPage($this->criteria(page: 2, limit: 20));

        self::assertEquals(["match_all" => new \stdClass()], $body["query"]);
        self::assertSame(20, $body["from"]);
        self::assertSame(20, $body["size"]);
        self::assertTrue($body["track_total_hits"]);
    }

    public function testLegacySectionIdIsCombinedWithSectionIdsAndDeduplicated(): void
    {
        self::assertSame(
            ["bool" => ["filter" => [["terms" => ["section_ids" => [12, 24]]]]]],
            $this->builder->build($this->criteria(sectionId: 12, sectionIds: [12, 24]))["query"],
        );
    }

    public function testEmptyFilterListsDoNotCreateTermsQueries(): void
    {
        self::assertEquals(
            ["match_all" => new \stdClass()],
            $this->builder->build($this->criteria(sectionIds: [], priceTypeCodes: []))["query"],
        );
    }

    public function testUsesConfiguredResultWindowForDeepPaginationDecision(): void
    {
        $builder = new CatalogSearchQueryBuilder(100);

        self::assertFalse($builder->requiresDeepPagination(new CatalogListCriteria(null, null, 10, 10, false)));
        self::assertTrue($builder->requiresDeepPagination(new CatalogListCriteria(null, null, 11, 10, false)));
    }

    /**
     * @param list<int> $sectionIds
     * @param list<string> $priceTypeCodes
     */
    private function criteria(
        ?int $sectionId = null,
        ?bool $active = null,
        int $page = 1,
        int $limit = 20,
        bool $lookAhead = false,
        ?string $query = null,
        array $sectionIds = [],
        ?int $priceFrom = null,
        ?int $priceTo = null,
        array $priceTypeCodes = [],
        ?bool $inStock = null,
    ): CatalogListCriteria {
        return new CatalogListCriteria(
            sectionId: $sectionId,
            active: $active,
            page: $page,
            limit: $limit,
            lookAhead: $lookAhead,
            query: $query,
            sectionIds: $sectionIds,
            priceFrom: $priceFrom,
            priceTo: $priceTo,
            priceTypeCodes: $priceTypeCodes,
            inStock: $inStock,
        );
    }
}
