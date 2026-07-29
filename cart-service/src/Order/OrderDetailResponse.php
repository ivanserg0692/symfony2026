<?php

namespace App\Order;

final readonly class OrderDetailResponse
{
    /**
     * @param list<OrderItemDetailResponse> $items
     */
    public function __construct(
        public int $id,
        public string $status,
        public string $totalPrice,
        public string $totalDiscount,
        public string $finalPrice,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public array $items,
    ) {
    }
}
