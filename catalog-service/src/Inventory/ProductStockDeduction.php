<?php

namespace App\Inventory;

final class ProductStockDeduction
{
    /** @var array<int, StoreStockDeduction> */
    private array $storesById = [];

    /**
     * @param list<StoreStockDeduction> $stores
     */
    public function __construct(
        private int $productId,
        private int $totalDeductedQuantity,
        array $stores,
        private int $productSnapshotId = 0,
    ) {
        foreach ($stores as $store) {
            $this->addStore($store);
        }
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getTotalDeductedQuantity(): int
    {
        return $this->totalDeductedQuantity;
    }

    /** @return list<StoreStockDeduction> */
    public function getStores(): array
    {
        ksort($this->storesById);

        return array_values($this->storesById);
    }

    public function getProductSnapshotId(): int
    {
        return $this->productSnapshotId;
    }

    public function addDeduction(self $deduction): void
    {
        if ($this->productId !== $deduction->productId) {
            throw new \InvalidArgumentException('Cannot merge deductions for different products.');
        }

        $this->totalDeductedQuantity += $deduction->totalDeductedQuantity;

        foreach ($deduction->storesById as $store) {
            $this->addStore($store);
        }
    }

    public function withProductSnapshotId(int $productSnapshotId): self
    {
        return new self(
            $this->productId,
            $this->totalDeductedQuantity,
            $this->getStores(),
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
            "stores" => array_map(static fn (StoreStockDeduction $store): array => $store->toPayload(), $this->getStores()),
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

    private function addStore(StoreStockDeduction $store): void
    {
        $storeId = $store->storeId;

        $this->storesById[$storeId] = isset($this->storesById[$storeId])
            ? new StoreStockDeduction($storeId, $this->storesById[$storeId]->deductedQuantity + $store->deductedQuantity)
            : $store;
    }
}
