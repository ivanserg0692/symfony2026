<?php

namespace App\Cart;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Validator\Constraints as Assert;

#[Exclude]
final readonly class GuestCartTransferItem
{
    public function __construct(
        #[Assert\Range(min: 1, max: 2147483647)]
        public int $productId,
        #[Assert\Range(min: 1, max: 2147483647)]
        public int $quantity,
    ) {
    }
}
