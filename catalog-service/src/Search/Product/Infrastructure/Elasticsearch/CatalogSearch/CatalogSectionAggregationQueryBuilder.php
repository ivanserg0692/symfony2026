<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch;

final class CatalogSectionAggregationQueryBuilder
{
    private const int BATCH_SIZE = 100;

    /** @return array<string, mixed> */
    public function activePage(string $pit, ?array $afterKey): array
    {
        $composite = [
            "size" => self::BATCH_SIZE,
            "sources" => [["id" => ["terms" => ["field" => "sections.id"]]]],
        ];
        if ($afterKey !== null) {
            $composite["after"] = $afterKey;
        }

        return [
            "size" => 0,
            "pit" => ["id" => $pit, "keep_alive" => "1m"],
            "aggs" => ["sections" => [
                "nested" => ["path" => "sections"],
                "aggs" => ["active" => [
                    "filter" => ["term" => ["sections.active" => true]],
                    "aggs" => ["by_id" => [
                        "composite" => $composite,
                        "aggs" => ["section" => ["top_hits" => ["size" => 1]]],
                    ]],
                ]],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public function byId(int $id): array
    {
        return [
            "size" => 0,
            "query" => ["nested" => [
                "path" => "sections",
                "query" => ["term" => ["sections.id" => $id]],
                "score_mode" => "none",
            ]],
            "aggs" => ["sections" => [
                "nested" => ["path" => "sections"],
                "aggs" => ["matching" => [
                    "filter" => ["term" => ["sections.id" => $id]],
                    "aggs" => ["section" => ["top_hits" => ["size" => 1]]],
                ]],
            ]],
        ];
    }
}
