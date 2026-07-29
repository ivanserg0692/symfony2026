<?php

namespace App\Order;

class InvalidProductSnapshotsResponseException extends \RuntimeException
{
    public function __construct(
        string $message = "Catalog product snapshot response is invalid.",
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
