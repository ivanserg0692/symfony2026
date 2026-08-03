<?php

namespace App\Inventory;

final readonly class StoreStockDeduction
{
    public function __construct(
        public int $storeId,
        public int $deductedQuantity,
    ) {
    }

    /**
     * @return array{storeId: int, deductedQuantity: int}
     */
    public function toPayload(): array
    {
        return [
            "storeId" => $this->storeId,
            "deductedQuantity" => $this->deductedQuantity,
        ];
    }

    /**
     * @param array{storeId: int|string, deductedQuantity: int|string} $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self((int) $payload["storeId"], (int) $payload["deductedQuantity"]);
    }
}
