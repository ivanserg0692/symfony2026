<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch;

final readonly class CatalogSearchHit
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

    /** @return array<string, mixed> */
    public function source(): array
    {
        return $this->payload["_source"] ?? [];
    }

    /** @return list<int|float|string> */
    public function sortValues(): array
    {
        return $this->payload["sort"] ?? [];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }
}
