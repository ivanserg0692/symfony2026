<?php

namespace App\Inventory;

final readonly class StockDeductionRequestItem
{
    public function __construct(
        public int $productId,
        public int $requestedQuantity,
        public ?int $storeId,
    ) {
    }
}
