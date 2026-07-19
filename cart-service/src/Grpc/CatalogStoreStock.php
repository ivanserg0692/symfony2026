<?php

namespace App\Grpc;

final readonly class CatalogStoreStock
{
    public function __construct(
        public int $storeId,
        public int $availableQuantity,
    ) {
    }
}
