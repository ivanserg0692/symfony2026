<?php

namespace App\Pricing;

use App\Entity\ProductPrice;
use App\Repository\CatalogElementsRepository;
use App\Repository\ProductPriceRepository;
use App\RoadRunner\Grpc\GrpcHandlerTiming;

final readonly class CheckoutProductPriceProvider
{
    private const BASE_PRICE_TYPE = "BASE";
    private const SALE_PRICE_TYPE = "SALE";

    public function __construct(
        private CatalogElementsRepository $catalogElementsRepository,
        private ProductPriceRepository $productPriceRepository,
        private GrpcHandlerTiming $handlerTiming,
    ) {
    }

    /**
     * @param int[] $productIds
     *
     * @return array<int, CheckoutProductPrice>
     */
    public function getPricesForProducts(array $productIds): array
    {
        $this->handlerTiming->mark('pricing.entered');
        $this->ensureProductsExist($productIds);
        $this->handlerTiming->mark('pricing.products_verified');

        $pricesByProduct = [];
        $prices = $this->productPriceRepository->findActivePricesForProducts(
            $productIds,
            [self::BASE_PRICE_TYPE, self::SALE_PRICE_TYPE],
            new \DateTimeImmutable(),
        );
        $this->handlerTiming->mark('pricing.prices_loaded');
        foreach ($prices as $price) {
            $product = $price->getProduct();
            $priceType = $price->getPriceType();

            if ($product?->getId() === null || $priceType?->getCode() === null || $price->getPrice() === null) {
                continue;
            }

            $pricesByProduct[$product->getId()][$priceType->getCode()] = $price;
        }
        $this->handlerTiming->mark('pricing.prices_grouped');

        $checkoutPrices = [];
        foreach ($productIds as $productId) {
            $checkoutPrices[$productId] = $this->createCheckoutPrice($productId, $pricesByProduct[$productId] ?? []);
        }
        $this->handlerTiming->mark('pricing.result_built');

        return $checkoutPrices;
    }

    /**
     * @param int[] $productIds
     */
    private function ensureProductsExist(array $productIds): void
    {
        $existingIds = $this->catalogElementsRepository->findExistingIds($productIds);
        $missingIds = array_values(array_diff($productIds, $existingIds));

        if ($missingIds !== []) {
            throw new CheckoutProductNotFoundException((int) $missingIds[0]);
        }
    }

    /**
     * @param array<string, ProductPrice> $pricesByType
     */
    private function createCheckoutPrice(int $productId, array $pricesByType): CheckoutProductPrice
    {
        $basePrice = $pricesByType[self::BASE_PRICE_TYPE] ?? null;

        if (!$basePrice instanceof ProductPrice || $basePrice->getPrice() === null) {
            throw new CheckoutProductPriceUnavailableException($productId);
        }

        $unitPriceMinorUnits = $basePrice->getPrice();
        $salePrice = $pricesByType[self::SALE_PRICE_TYPE] ?? null;
        $finalUnitPriceMinorUnits = $salePrice instanceof ProductPrice && $salePrice->getPrice() !== null
            ? min($unitPriceMinorUnits, $salePrice->getPrice())
            : $unitPriceMinorUnits;
        $unitDiscountMinorUnits = max(0, $unitPriceMinorUnits - $finalUnitPriceMinorUnits);

        return new CheckoutProductPrice(
            $productId,
            $unitPriceMinorUnits,
            $unitDiscountMinorUnits,
            $finalUnitPriceMinorUnits,
        );
    }
}
