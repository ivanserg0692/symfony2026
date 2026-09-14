<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch;

use App\Search\Product\Application\Dto\Read\CatalogListCriteria;

final readonly class CatalogSearchQueryBuilder
{
    private const RESULT_WINDOW = 10000;
    private const SEEK_BATCH_SIZE = 1000;

    /** @return array<string, mixed> */
    public function build(CatalogListCriteria $criteria): array
    {
        return [
            "query" => $this->filters($criteria),
            // PostgreSQL DESC places NULL first.
            "sort" => [["sort" => ["order" => "desc", "missing" => "_first"]], ["id" => "asc"]],
            "track_total_hits" => !$criteria->lookAhead,
            "size" => $criteria->getFetchSize(),
        ];
    }

    public function requiresDeepPagination(CatalogListCriteria $criteria): bool
    {
        return $criteria->getOffset() + $criteria->getFetchSize() > self::RESULT_WINDOW;
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
    private function filters(CatalogListCriteria $criteria): array
    {
        $filters = [];
        if ($criteria->sectionId !== null) {
            $filters[] = ["term" => ["section_ids" => $criteria->sectionId]];
        }
        if ($criteria->active !== null) {
            $filters[] = ["term" => ["active" => $criteria->active]];
        }

        // Future correlated price/store conditions belong in one nested query
        // per relation here, independently of pagination and response mapping.
        return $filters === [] ? ["match_all" => new \stdClass()] : ["bool" => ["filter" => $filters]];
    }
}
