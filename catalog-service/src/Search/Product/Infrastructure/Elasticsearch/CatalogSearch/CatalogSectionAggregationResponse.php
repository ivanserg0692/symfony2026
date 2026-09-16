<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch;

use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;

final readonly class CatalogSectionAggregationResponse
{
    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload)
    {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    public function assertComplete(): void
    {
        if (($this->payload["timed_out"] ?? false) || ($this->payload["_shards"]["failed"] ?? 0) > 0) {
            throw new \RuntimeException("Elasticsearch returned incomplete catalog section results.");
        }
    }

    public function pitId(): ?string
    {
        return isset($this->payload["pit_id"]) ? (string) $this->payload["pit_id"] : null;
    }

    /** @return list<CatalogSectionResponse> */
    public function activeSections(): array
    {
        $aggregation = $this->payload["aggregations"]["sections"]["active"]["by_id"] ?? null;
        if (!is_array($aggregation) || !isset($aggregation["buckets"]) || !is_array($aggregation["buckets"])) {
            throw new \RuntimeException("Elasticsearch catalog section aggregation is missing buckets.");
        }

        return array_map(
            static fn (array $bucket): CatalogSectionResponse => self::sectionFromHit($bucket["section"]["hits"]["hits"][0] ?? null),
            $aggregation["buckets"],
        );
    }

    /** @return array<string, mixed>|null */
    public function nextPageKey(): ?array
    {
        $aggregation = $this->payload["aggregations"]["sections"]["active"]["by_id"] ?? null;

        return is_array($aggregation) ? ($aggregation["after_key"] ?? null) : null;
    }

    public function section(): ?CatalogSectionResponse
    {
        $hits = $this->payload["aggregations"]["sections"]["matching"]["section"]["hits"]["hits"] ?? null;
        if (!is_array($hits)) {
            throw new \RuntimeException("Elasticsearch catalog section aggregation is missing hits.");
        }

        return isset($hits[0]) ? self::sectionFromHit($hits[0]) : null;
    }

    /** @param array<string, mixed>|null $hit */
    private static function sectionFromHit(?array $hit): CatalogSectionResponse
    {
        if (!isset($hit["_source"]) || !is_array($hit["_source"])) {
            throw new \RuntimeException("Elasticsearch catalog section aggregation has no source.");
        }

        return CatalogSectionResponse::fromDocument($hit["_source"]);
    }
}
