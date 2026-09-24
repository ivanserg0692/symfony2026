<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\Bulk;

final readonly class BulkResponse
{
    /**
     * @param BulkItemResponse[] $items
     */
    private function __construct(
        public array $items,
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromArray(array $response): self
    {
        $rawItems = is_array($response["items"] ?? null) ? $response["items"] : [];
        $items = [];

        foreach ($rawItems as $rawItem) {
            $items[] = BulkItemResponse::fromArray($rawItem);
        }

        return new self($items);
    }
}
