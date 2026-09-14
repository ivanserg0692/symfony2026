<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch;

use App\Search\Product\Application\Dto\Read\CatalogListCriteria;

final readonly class CatalogSearchQueryBuilder
{
    private const SEEK_BATCH_SIZE = 1000;

    public function __construct(
        private int $productSearchResultWindow,
    ) {
        if ($this->productSearchResultWindow < 1) {
            throw new \InvalidArgumentException("PRODUCT_SEARCH_RESULT_WINDOW must be greater than zero.");
        }
    }

    /** @return array<string, mixed> */
    public function build(CatalogListCriteria $criteria): array
    {
        return [
            "query" => $this->query($criteria),
            // PostgreSQL DESC places NULL first.
            "sort" => [["sort" => ["order" => "desc", "missing" => "_first"]], ["id" => "asc"]],
            "track_total_hits" => !$criteria->lookAhead,
            "size" => $criteria->getFetchSize(),
        ];
    }

    public function requiresDeepPagination(CatalogListCriteria $criteria): bool
    {
        return $criteria->getOffset() + $criteria->getFetchSize() > $this->productSearchResultWindow;
    }

    /** @return array<string, mixed> */
    public function buildOffsetPage(CatalogListCriteria $criteria): array
    {
        return $this->build($criteria) + ["from" => $criteria->getOffset()];
    }

    /**
     * @param list<int|float|string> $searchAfter
     * @return array<string, mixed>
     */
    public function buildDeepPage(
        CatalogListCriteria $criteria,
        string $pit,
        int $remaining,
        ?array $searchAfter,
        bool $includeSource,
        bool $trackTotalHits,
    ): array {
        $body = $this->build($criteria);
        $body["pit"] = ["id" => $pit, "keep_alive" => "1m"];
        $body["size"] = $this->deepPageSize($criteria, $remaining);
        $body["_source"] = $includeSource;
        $body["track_total_hits"] = $trackTotalHits;
        if ($searchAfter !== null) {
            $body["search_after"] = $searchAfter;
        }

        return $body;
    }

    public function deepPageSize(CatalogListCriteria $criteria, int $remaining): int
    {
        return $remaining > 0 ? min(self::SEEK_BATCH_SIZE, $remaining) : $criteria->getFetchSize();
    }

    /** @return array<string, mixed> */
    private function query(CatalogListCriteria $criteria): array
    {
        $must = $this->buildMustClauses($criteria);
        $filters = $this->buildFilterClauses($criteria);

        if ($must === [] && $filters === []) {
            return ["match_all" => new \stdClass()];
        }

        $bool = [];
        if ($must !== []) {
            $bool["must"] = $must;
        }
        if ($filters !== []) {
            $bool["filter"] = $filters;
        }

        return ["bool" => $bool];
    }

    /** @return list<array<string, mixed>> */
    private function buildMustClauses(CatalogListCriteria $criteria): array
    {
        return $criteria->query === null
            ? []
            : [["multi_match" => [
                "query" => $criteria->query,
                "fields" => ["name^3", "description"],
            ]]];
    }

    /** @return list<array<string, mixed>> */
    private function buildFilterClauses(CatalogListCriteria $criteria): array
    {
        return array_values(array_filter([
            $this->buildSectionFilter($criteria),
            $this->buildActiveFilter($criteria),
            $this->buildPriceFilter($criteria),
            $this->buildStockFilter($criteria),
        ], static fn (?array $filter): bool => $filter !== null));
    }

    /** @return array<string, mixed>|null */
    private function buildSectionFilter(CatalogListCriteria $criteria): ?array
    {
        $sectionIds = $criteria->sectionIds;
        if ($criteria->sectionId !== null) {
            $sectionIds[] = $criteria->sectionId;
        }
        $sectionIds = array_values(array_unique($sectionIds));

        return $sectionIds === [] ? null : ["terms" => ["section_ids" => $sectionIds]];
    }

    /** @return array<string, mixed>|null */
    private function buildActiveFilter(CatalogListCriteria $criteria): ?array
    {
        return $criteria->active === null ? null : ["term" => ["active" => $criteria->active]];
    }

    /** @return array<string, mixed>|null */
    private function buildPriceFilter(CatalogListCriteria $criteria): ?array
    {
        $priceFilters = [];
        if ($criteria->priceTypeCodes !== []) {
            $priceFilters[] = ["terms" => ["prices.type_code" => $criteria->priceTypeCodes]];
        }
        if ($criteria->priceFrom !== null || $criteria->priceTo !== null) {
            $range = [];
            if ($criteria->priceFrom !== null) {
                $range["gte"] = $criteria->priceFrom;
            }
            if ($criteria->priceTo !== null) {
                $range["lte"] = $criteria->priceTo;
            }
            $priceFilters[] = ["range" => ["prices.amount" => $range]];
        }

        if ($priceFilters === []) {
            return null;
        }

        return ["nested" => [
            "path" => "prices",
            "query" => ["bool" => ["filter" => $priceFilters]],
        ]];
    }

    /** @return array<string, mixed>|null */
    private function buildStockFilter(CatalogListCriteria $criteria): ?array
    {
        if ($criteria->inStock === null) {
            return null;
        }

        $operator = $criteria->inStock ? "gt" : "lte";

        return ["range" => ["total_stock" => [$operator => 0]]];
    }
}
