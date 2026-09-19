<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch;

final readonly class CatalogSearchResponse
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

    /** @return list<CatalogSearchHit> */
    public function hits(): array
    {
        return array_map(
            static fn (array $hit): CatalogSearchHit => CatalogSearchHit::fromArray($hit),
            $this->payload["hits"]["hits"] ?? [],
        );
    }

    public function pitId(): ?string
    {
        return isset($this->payload["pit_id"]) ? (string) $this->payload["pit_id"] : null;
    }

    public function assertComplete(): void
    {
        if (($this->payload["timed_out"] ?? false) || ($this->payload["_shards"]["failed"] ?? 0) > 0) {
            throw new \RuntimeException("Elasticsearch returned incomplete catalog search results.");
        }
    }

    public function exactTotal(): int
    {
        if (($this->payload["hits"]["total"]["relation"] ?? null) !== "eq") {
            throw new \RuntimeException("Elasticsearch did not return an exact catalog total.");
        }

        return $this->payload["hits"]["total"]["value"];
    }

    /** @param list<CatalogSearchHit> $hits */
    public function withHits(array $hits): self
    {
        $payload = $this->payload;
        $payload["hits"]["hits"] = array_map(
            static fn (CatalogSearchHit $hit): array => $hit->payload(),
            $hits,
        );

        return new self($payload);
    }

    public function withExactTotal(int $total): self
    {
        $payload = $this->payload;
        $payload["hits"]["total"] = ["value" => $total, "relation" => "eq"];

        return new self($payload);
    }
}
