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
    ) {
    }

    /**
     * @return array{productId: int, totalDeductedQuantity: int, stores: list<array{storeId: int, deductedQuantity: int}>}
     */
    public function toPayload(): array
    {
        return [
            "productId" => $this->productId,
            "totalDeductedQuantity" => $this->totalDeductedQuantity,
            "stores" => array_map(static fn (StoreStockDeduction $store): array => $store->toPayload(), $this->stores),
        ];
    }

    /**
     * @param array{productId: int|string, totalDeductedQuantity: int|string, stores: list<array{storeId: int|string, deductedQuantity: int|string}>} $payload
     */
    public static function fromPayload(array $payload): self
    {
        $stores = [];

        foreach ($payload["stores"] as $storePayload) {
            $stores[] = StoreStockDeduction::fromPayload($storePayload);
        }

        return new self((int) $payload["productId"], (int) $payload["totalDeductedQuantity"], $stores);
    }
}
