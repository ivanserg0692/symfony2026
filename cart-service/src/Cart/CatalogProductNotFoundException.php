<?php

namespace App\Cart;

class CatalogProductNotFoundException extends \RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Product was not found.");
    }
}
