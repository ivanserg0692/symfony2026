<?php

namespace App\Grpc;

final readonly class CatalogProductPrice
{
    public function __construct(
        public int $productId,
        public int $unitPriceMinorUnits,
        public int $unitDiscountMinorUnits,
        public int $finalUnitPriceMinorUnits,
    ) {
    }
}
