<?php

namespace App\Grpc;

final readonly class CatalogStoreDeduction
{
    public function __construct(
        public int $storeId,
        public int $deductedQuantity,
    ) {
    }
}
