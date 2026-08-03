<?php

namespace App\Cart;

use App\Entity\CartItem;

final readonly class CartItemMutationResult
{
    public function __construct(
        public CartItem $item,
        public bool $created,
    ) {
    }
}
