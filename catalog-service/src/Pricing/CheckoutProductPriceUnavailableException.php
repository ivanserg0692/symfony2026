<?php

namespace App\Pricing;

final class CheckoutProductPriceUnavailableException extends \RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct(sprintf("Product %d does not have an active checkout price.", $productId));
    }
}
