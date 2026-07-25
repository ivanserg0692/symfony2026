<?php

namespace App\Order;

use App\Order\OrderStatus;

final class OrderCancellationNotAllowedException extends \RuntimeException
{
    public function __construct(public readonly OrderStatus $status)
    {
        parent::__construct(sprintf("Order cannot be canceled from %s status.", $status->value));
    }
}
