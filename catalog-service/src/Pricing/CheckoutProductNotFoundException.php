<?php

namespace App\Pricing;

final class CheckoutProductNotFoundException extends \RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct(sprintf("Product %d was not found.", $productId));
    }
}
