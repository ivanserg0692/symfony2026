<?php

namespace App\Inventory;

final readonly class ProductStockDeduction
{
    /**
     * @param list<StoreStockDeduction> $stores
     */
    public function __construct(
        public int $productId,
        public int $totalDeductedQuantity,
        public array $stores,
        public int $productSnapshotId = 0,
    ) {
    }

    public function withProductSnapshotId(int $productSnapshotId): self
    {
        return new self(
            $this->productId,
            $this->totalDeductedQuantity,
            $this->stores,
            $productSnapshotId,
        );
    }

    /**
     * @return array{productId: int, totalDeductedQuantity: int, stores: list<array{storeId: int, deductedQuantity: int}>, productSnapshotId: int}
     */
    public function toPayload(): array
    {
        return [
            "productId" => $this->productId,
            "totalDeductedQuantity" => $this->totalDeductedQuantity,
            "stores" => array_map(static fn (StoreStockDeduction $store): array => $store->toPayload(), $this->stores),
            "productSnapshotId" => $this->productSnapshotId,
        ];
    }

    /**
     * @param array{productId: int|string, totalDeductedQuantity: int|string, stores: list<array{storeId: int|string, deductedQuantity: int|string}>, productSnapshotId?: int|string} $payload
     */
    public static function fromPayload(array $payload): self
    {
        $stores = [];

        foreach ($payload["stores"] as $storePayload) {
            $stores[] = StoreStockDeduction::fromPayload($storePayload);
        }

        return new self(
            (int) $payload["productId"],
            (int) $payload["totalDeductedQuantity"],
            $stores,
            (int) ($payload["productSnapshotId"] ?? 0),
        );
    }
}
