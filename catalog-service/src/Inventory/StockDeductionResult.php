<?php

namespace App\Inventory;

final readonly class StockDeductionResult
{
    /**
     * @param list<ProductStockDeduction> $products
     */
    public function __construct(
        public string $operationId,
        public array $products,
    ) {
    }

    /**
     * @return array{operationId: string, products: list<array{productId: int, totalDeductedQuantity: int, stores: list<array{storeId: int, deductedQuantity: int}>}>}
     */
    public function toPayload(): array
    {
        return [
            "operationId" => $this->operationId,
            "products" => array_map(static fn (ProductStockDeduction $product): array => $product->toPayload(), $this->products),
        ];
    }

    /**
     * @param array{operationId: string, products: list<array{productId: int|string, totalDeductedQuantity: int|string, stores: list<array{storeId: int|string, deductedQuantity: int|string}>}>} $payload
     */
    public static function fromPayload(array $payload): self
    {
        $products = [];

        foreach ($payload["products"] as $productPayload) {
            $products[] = ProductStockDeduction::fromPayload($productPayload);
        }

        return new self($payload["operationId"], $products);
    }
}
