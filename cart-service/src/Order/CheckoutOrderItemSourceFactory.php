<?php

namespace App\Order;

use App\Grpc\CatalogDeductStocksResult;
use App\Grpc\CatalogProductDeduction;
use App\Grpc\CatalogProductPrice;

final readonly class CheckoutOrderItemSourceFactory
{
    /**
     * @param list<CatalogProductPrice> $prices
     */
    public function create(
        CheckoutCartItems $items,
        array $prices,
        CatalogDeductStocksResult $deductionResult,
    ): CheckoutOrderItemSource {
        $expectedProductIds = $items->getProductIds();

        return new CheckoutOrderItemSource(
            $items,
            $this->pricesByProductId($expectedProductIds, $prices),
            $this->deductionsByProductId($expectedProductIds, $deductionResult),
        );
    }

    /**
     * @param list<int> $expectedProductIds
     * @param list<CatalogProductPrice> $prices
     *
     * @return array<int, CatalogProductPrice>
     */
    private function pricesByProductId(array $expectedProductIds, array $prices): array
    {
        $pricesByProductId = [];

        foreach ($prices as $price) {
            if (!in_array($price->productId, $expectedProductIds, true)) {
                throw new InvalidProductPricesResponseException("Catalog returned unexpected product price.");
            }

            if (isset($pricesByProductId[$price->productId])) {
                throw new InvalidProductPricesResponseException("Catalog returned duplicate product price.");
            }

            $this->validateMoneyMinorUnits($price->unitPriceMinorUnits);
            $this->validateMoneyMinorUnits($price->unitDiscountMinorUnits);
            $this->validateMoneyMinorUnits($price->finalUnitPriceMinorUnits);

            $pricesByProductId[$price->productId] = $price;
        }

        foreach ($expectedProductIds as $productId) {
            if (!isset($pricesByProductId[$productId])) {
                throw new InvalidProductPricesResponseException("Catalog did not return all product prices.");
            }
        }

        return $pricesByProductId;
    }

    /**
     * @param list<int> $expectedProductIds
     *
     * @return array<int, CatalogProductDeduction>
     */
    private function deductionsByProductId(array $expectedProductIds, CatalogDeductStocksResult $deductionResult): array
    {
        $deductionsByProductId = [];

        foreach ($deductionResult->products as $product) {
            if (!in_array($product->productId, $expectedProductIds, true)) {
                throw new InvalidDeductStocksResponseException("Inventory returned unexpected product deduction.");
            }

            if (isset($deductionsByProductId[$product->productId])) {
                throw new InvalidDeductStocksResponseException("Inventory returned duplicate product deduction.");
            }

            if ($product->productSnapshotId <= 0) {
                throw new InvalidDeductStocksResponseException("Inventory returned empty product snapshot id.");
            }

            $deductionsByProductId[$product->productId] = $product;
        }

        foreach ($expectedProductIds as $productId) {
            if (!isset($deductionsByProductId[$productId])) {
                throw new InvalidDeductStocksResponseException("Inventory did not return all product deductions.");
            }
        }

        return $deductionsByProductId;
    }

    private function validateMoneyMinorUnits(int $money): void
    {
        if ($money < 0) {
            throw new InvalidProductPricesResponseException("Catalog returned invalid money value.");
        }
    }
}
