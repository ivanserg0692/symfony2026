<?php

namespace App\Pricing;

final readonly class CheckoutProductPrice
{
    public function __construct(
        public int $productId,
        public int $unitPriceMinorUnits,
        public int $unitDiscountMinorUnits,
        public int $finalUnitPriceMinorUnits,
    ) {
    }
}
