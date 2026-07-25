<?php

namespace App\Order;

final class EmptyCartException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct("Cart is empty.");
    }
}
