<?php

namespace App\Cart;

use App\Entity\CartItem;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class CartTransferItems
{
    /** @var array<int, CartItem> Positions keyed by product ID. */
    private array $byProductId = [];

    /**
     * @param list<CartItem> $items Positions loaded from the cart repository.
     */
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            /** @var int $productId The database requires a product ID for persisted positions. */
            $productId = $item->getCatalogElementId();
            $this->byProductId[$productId] = $item;
        }
    }

    public function getByProductId(int $productId): ?CartItem
    {
        return $this->byProductId[$productId] ?? null;
    }

    /**
     * @param array<int, int> $quantities Final quantities keyed by product ID.
     * @return array<int, CartItem> Existing positions whose product IDs are absent from $quantities.
     */
    public function getItemsAbsentFrom(array $quantities): array
    {
        return array_diff_key($this->byProductId, $quantities);
    }
}
