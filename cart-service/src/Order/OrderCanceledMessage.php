<?php

namespace App\Order;

final readonly class OrderCanceledMessage
{
    public function __construct(
        public int $orderId,
        public int $ownerId,
        public \DateTimeImmutable $canceledAt,
        public ?string $operationId = null,
    ) {
    }
}
