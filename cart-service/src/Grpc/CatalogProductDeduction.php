<?php

namespace App\Grpc;

final readonly class CatalogProductDeduction
{
    /**
     * @param list<CatalogStoreDeduction> $stores
     */
    public function __construct(
        public int $productId,
        public int $totalDeductedQuantity,
        public int $productSnapshotId,
        public array $stores,
    ) {
    }
}
