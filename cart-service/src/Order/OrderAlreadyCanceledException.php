<?php

namespace App\Order;

final class OrderAlreadyCanceledException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct("Order was already canceled.");
    }
}
