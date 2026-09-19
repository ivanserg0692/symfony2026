<?php

namespace App\Tests\Unit\Inventory;

use App\Inventory\ProductStockDeduction;
use App\Inventory\StoreStockDeduction;
use PHPUnit\Framework\TestCase;

final class ProductStockDeductionTest extends TestCase
{
    public function testMergesQuantitiesByStoreAndPreservesPayloadAfterSnapshotAttachment(): void
    {
        $deduction = new ProductStockDeduction(42, 2, [new StoreStockDeduction(9, 2)]);

        $deduction->addDeduction(new ProductStockDeduction(42, 4, [
            new StoreStockDeduction(9, 3),
            new StoreStockDeduction(3, 1),
        ]));

        $payload = [
            'productId' => 42,
            'totalDeductedQuantity' => 6,
            'stores' => [
                ['storeId' => 3, 'deductedQuantity' => 1],
                ['storeId' => 9, 'deductedQuantity' => 5],
            ],
            'productSnapshotId' => 55,
        ];

        self::assertSame($payload, $deduction->withProductSnapshotId(55)->toPayload());
        self::assertSame($payload, ProductStockDeduction::fromPayload($payload)->toPayload());
    }
}
