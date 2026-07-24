<?php

namespace App\Grpc;

final readonly class CatalogProductPrice
{
    public function __construct(
        public int $productId,
        public string $unitPrice,
        public string $unitDiscount,
        public string $finalUnitPrice,
    ) {
    }
}
