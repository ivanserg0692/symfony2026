<?php

namespace App\Order;

final class ActiveCartNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct("Active cart was not found.");
    }
}
