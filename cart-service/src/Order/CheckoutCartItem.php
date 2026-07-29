<?php

namespace App\Order;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckoutCartItem
{
    public function __construct(
        #[Assert\Positive(message: "Cart item has invalid product id.")]
        public int $productId,
        #[Assert\Positive(message: "Cart item has invalid quantity.")]
        public int $quantity,
        public int $sort,
    ) {
    }
}
