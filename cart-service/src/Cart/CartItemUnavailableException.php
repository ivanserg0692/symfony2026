<?php

namespace App\Cart;

class CartItemUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
    ) {
        parent::__construct("Requested quantity is unavailable");
    }
}
