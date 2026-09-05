<?php

namespace App\Search\Product\Infrastructure\Messenger;

/**
 * Transactional staging message written to PostgreSQL by Doctrine transport.
 */
final readonly class ProductSearchOutboxEvent
{
    public function __construct(
        public int $catalogElementId,
    ) {
        if ($this->catalogElementId < 1) {
            throw new \InvalidArgumentException("Catalog element ID must be greater than zero.");
        }
    }
}
