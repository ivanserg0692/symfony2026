<?php

namespace App\Tests\Unit\Order;

use App\Grpc\CatalogDeductStocksResult;
use App\Grpc\CatalogProductDeduction;
use App\Grpc\CatalogProductPrice;
use App\Order\CheckoutCartItem;
use App\Order\CheckoutCartItems;
use App\Order\CheckoutOrderItemSourceFactory;
use App\Order\InvalidDeductStocksResponseException;
use App\Order\InvalidProductPricesResponseException;
use PHPUnit\Framework\TestCase;

final class CheckoutOrderItemSourceFactoryTest extends TestCase
{
    public function testCreatesLookupResultByProductId(): void
    {
        $factory = new CheckoutOrderItemSourceFactory();
        $items = new CheckoutCartItems([
            new CheckoutCartItem(10, 2, 1),
            new CheckoutCartItem(20, 1, 2),
        ]);
        $price = new CatalogProductPrice(10, 1000, 100, 900);
        $deduction = new CatalogProductDeduction(20, 1, 502, []);

        $result = $factory->create($items, [
            $price,
            new CatalogProductPrice(20, 2000, 0, 2000),
        ], new CatalogDeductStocksResult('op-1', [
            new CatalogProductDeduction(10, 2, 501, []),
            $deduction,
        ]));

        self::assertSame($items, $result->getCartItems());
        self::assertSame($price, $result->getPriceForProduct(10));
        self::assertSame($deduction, $result->getDeductionForProduct(20));
    }

    public function testRejectsDuplicatePrice(): void
    {
        $factory = new CheckoutOrderItemSourceFactory();

        $this->expectException(InvalidProductPricesResponseException::class);

        $factory->create($this->items(), [
            new CatalogProductPrice(10, 1000, 0, 1000),
            new CatalogProductPrice(10, 1000, 0, 1000),
        ], $this->deductionResult());
    }

    public function testRejectsMissingPrice(): void
    {
        $factory = new CheckoutOrderItemSourceFactory();

        $this->expectException(InvalidProductPricesResponseException::class);

        $factory->create($this->items(), [], $this->deductionResult());
    }

    public function testRejectsNegativeMoney(): void
    {
        $factory = new CheckoutOrderItemSourceFactory();

        $this->expectException(InvalidProductPricesResponseException::class);

        $factory->create($this->items(), [
            new CatalogProductPrice(10, 1000, 0, -1),
        ], $this->deductionResult());
    }

    public function testRejectsMissingDeduction(): void
    {
        $factory = new CheckoutOrderItemSourceFactory();

        $this->expectException(InvalidDeductStocksResponseException::class);

        $factory->create($this->items(), $this->prices(), new CatalogDeductStocksResult('op-1', []));
    }

    public function testRejectsEmptySnapshotId(): void
    {
        $factory = new CheckoutOrderItemSourceFactory();

        $this->expectException(InvalidDeductStocksResponseException::class);

        $factory->create($this->items(), $this->prices(), new CatalogDeductStocksResult('op-1', [
            new CatalogProductDeduction(10, 1, 0, []),
        ]));
    }

    private function items(): CheckoutCartItems
    {
        return new CheckoutCartItems([
            new CheckoutCartItem(10, 1, 1),
        ]);
    }

    /**
     * @return list<CatalogProductPrice>
     */
    private function prices(): array
    {
        return [
            new CatalogProductPrice(10, 1000, 0, 1000),
        ];
    }

    private function deductionResult(): CatalogDeductStocksResult
    {
        return new CatalogDeductStocksResult('op-1', [
            new CatalogProductDeduction(10, 1, 501, []),
        ]);
    }
}
