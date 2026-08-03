<?php

namespace App\Order;

enum OrderStatus: string
{
    case Pending = "pending";
    case Completed = "completed";
    case Canceled = "canceled";
}
