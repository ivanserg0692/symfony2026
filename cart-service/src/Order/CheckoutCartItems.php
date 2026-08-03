<?php

namespace App\Order;

/**
 * @implements \IteratorAggregate<int, CheckoutCartItem>
 */
final readonly class CheckoutCartItems implements \Countable, \IteratorAggregate
{
    /**
     * @param list<CheckoutCartItem> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    /**
     * @return list<int>
     */
    public function getProductIds(): array
    {
        $productIds = [];

        foreach ($this->items as $item) {
            $productIds[] = $item->productId;
        }

        return array_values(array_unique($productIds));
    }

    /**
     * @return list<array{productId: int, quantity: int}>
     */
    public function toDeductStocksItems(): array
    {
        $deductItems = [];

        foreach ($this->items as $item) {
            $deductItems[] = [
                "productId" => $item->productId,
                "quantity" => $item->quantity,
            ];
        }

        return $deductItems;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
