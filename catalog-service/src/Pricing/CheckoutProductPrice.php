<?php

namespace App\Pricing;

final readonly class CheckoutProductPrice
{
    public function __construct(
        public int $productId,
        public string $unitPrice,
        public string $unitDiscount,
        public string $finalUnitPrice,
    ) {
    }
}
