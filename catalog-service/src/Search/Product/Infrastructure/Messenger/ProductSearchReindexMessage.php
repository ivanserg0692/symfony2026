<?php

namespace App\Search\Product\Infrastructure\Messenger;

/**
 * Minimal RabbitMQ message consumed by the Elasticsearch indexing worker.
 */
final readonly class ProductSearchReindexMessage
{
    public function __construct(
        public int $catalogElementId,
    ) {
        if ($this->catalogElementId < 1) {
            throw new \InvalidArgumentException("Catalog element ID must be greater than zero.");
        }
    }
}
