<?php

namespace App\Notifier\Support;

final readonly class CreateNotificationMessage
{
    public function __construct(
        public int $recipientId,
        public string $message,
    ) {
    }
}
