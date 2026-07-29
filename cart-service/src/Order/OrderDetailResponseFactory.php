<?php

namespace App\Order;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Grpc\CatalogProductSnapshot;

final readonly class OrderDetailResponseFactory
{
    /**
     * @param array<int, CatalogProductSnapshot> $snapshotsById
     */
    public function create(Order $order, array $snapshotsById): OrderDetailResponse
    {
        $orderId = $order->getId();
        $totalPrice = $order->getTotalPrice();
        $totalDiscount = $order->getTotalDiscount();
        $finalPrice = $order->getFinalPrice();
        $createdAt = $order->getCreatedAt();
        $updatedAt = $order->getUpdatedAt();

        if (
            $orderId === null
            || $totalPrice === null
            || $totalDiscount === null
            || $finalPrice === null
            || $createdAt === null
            || $updatedAt === null
        ) {
            throw new InvalidProductSnapshotsResponseException("Order detail response cannot be built from an incomplete order.");
        }

        return new OrderDetailResponse(
            $orderId,
            $order->getStatus()->value,
            $totalPrice,
            $totalDiscount,
            $finalPrice,
            $createdAt,
            $updatedAt,
            array_map(
                fn (OrderItem $item): OrderItemDetailResponse => $this->createItem($item, $snapshotsById),
                $order->getItems()->toArray(),
            ),
        );
    }

    /**
     * @param array<int, CatalogProductSnapshot> $snapshotsById
     */
    private function createItem(OrderItem $item, array $snapshotsById): OrderItemDetailResponse
    {
        $itemId = $item->getId();
        $productSnapshotId = $item->getProductSnapshotId();
        $quantity = $item->getQuantity();
        $sort = $item->getSort();
        $unitPrice = $item->getUnitPrice();
        $unitDiscount = $item->getUnitDiscount();
        $finalUnitPrice = $item->getFinalUnitPrice();
        $lineTotal = $item->getLineTotal();
        $createdAt = $item->getCreatedAt();

        if (
            $itemId === null
            || $productSnapshotId === null
            || $quantity === null
            || $sort === null
            || $unitPrice === null
            || $unitDiscount === null
            || $finalUnitPrice === null
            || $lineTotal === null
            || $createdAt === null
            || !isset($snapshotsById[$productSnapshotId])
        ) {
            throw new InvalidProductSnapshotsResponseException("Order item snapshot response cannot be built from incomplete data.");
        }

        return new OrderItemDetailResponse(
            $itemId,
            $productSnapshotId,
            $quantity,
            $sort,
            $unitPrice,
            $unitDiscount,
            $finalUnitPrice,
            $lineTotal,
            $createdAt,
            $snapshotsById[$productSnapshotId],
        );
    }
}
