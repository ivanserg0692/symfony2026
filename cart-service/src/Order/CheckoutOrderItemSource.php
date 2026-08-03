<?php

namespace App\Order;

use App\Grpc\CatalogProductDeduction;
use App\Grpc\CatalogProductPrice;

final readonly class CheckoutOrderItemSource
{
    /**
     * @param array<int, CatalogProductPrice> $pricesByProductId
     * @param array<int, CatalogProductDeduction> $deductionsByProductId
     */
    public function __construct(
        private CheckoutCartItems $cartItems,
        private array $pricesByProductId,
        private array $deductionsByProductId,
    ) {
    }

    public function getCartItems(): CheckoutCartItems
    {
        return $this->cartItems;
    }

    public function getPriceForProduct(int $productId): CatalogProductPrice
    {
        return $this->pricesByProductId[$productId];
    }

    public function getDeductionForProduct(int $productId): CatalogProductDeduction
    {
        return $this->deductionsByProductId[$productId];
    }
}
