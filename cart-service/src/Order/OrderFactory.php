<?php

namespace App\Order;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Grpc\CatalogProductDeduction;

class OrderFactory
{
    public function createPendingOrder(int $ownerId, string $operationId, \DateTimeImmutable $now): Order
    {
        return (new Order())
            ->setOwnerId($ownerId)
            ->setStatus(OrderStatus::Pending)
            ->setOperationId($operationId)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
    }

    public function createItemFromCheckout(
        CheckoutCartItem $item,
        CatalogProductDeduction $deduction,
        int $unitPriceMinorUnits,
        int $unitDiscountMinorUnits,
        int $finalUnitPriceMinorUnits,
        int $lineTotalMinorUnits,
        \DateTimeImmutable $now,
    ): OrderItem {
        return (new OrderItem())
            ->setProductSnapshotId($deduction->productSnapshotId)
            ->setQuantity($item->quantity)
            ->setSort($item->sort)
            ->setUnitPrice($this->formatMinorUnits($unitPriceMinorUnits))
            ->setUnitDiscount($this->formatMinorUnits($unitDiscountMinorUnits))
            ->setFinalUnitPrice($this->formatMinorUnits($finalUnitPriceMinorUnits))
            ->setLineTotal($this->formatMinorUnits($lineTotalMinorUnits))
            ->setCreatedAt($now);
    }

    public function setTotalsFromMinorUnits(
        Order $order,
        int $totalPriceMinorUnits,
        int $totalDiscountMinorUnits,
        int $finalPriceMinorUnits,
    ): Order {
        return $order
            ->setTotalPrice($this->formatMinorUnits($totalPriceMinorUnits))
            ->setTotalDiscount($this->formatMinorUnits($totalDiscountMinorUnits))
            ->setFinalPrice($this->formatMinorUnits($finalPriceMinorUnits));
    }

    private function formatMinorUnits(int $minorUnits): string
    {
        return sprintf('%d.%02d', intdiv($minorUnits, 100), $minorUnits % 100);
    }
}
