<?php

namespace App\Order;

enum OrderStatus: string
{
    case Pending = "pending";
    case Canceled = "canceled";
    case Completed = "completed";
}
