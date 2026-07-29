<?php

namespace App\Order;

use App\Grpc\CatalogProductSnapshot;

final readonly class OrderItemDetailResponse
{
    public function __construct(
        public int $id,
        public int $productSnapshotId,
        public int $quantity,
        public int $sort,
        public string $unitPrice,
        public string $unitDiscount,
        public string $finalUnitPrice,
        public string $lineTotal,
        public \DateTimeImmutable $createdAt,
        public CatalogProductSnapshot $productSnapshot,
    ) {
    }
}
