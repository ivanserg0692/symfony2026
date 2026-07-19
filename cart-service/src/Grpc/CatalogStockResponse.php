<?php

namespace App\Grpc;

final readonly class CatalogStockResponse
{
    /**
     * @param list<CatalogStoreStock> $stores
     */
    public function __construct(
        public int $productId,
        public int $totalAvailableQuantity,
        public bool $available,
        public array $stores,
    ) {
    }
}
