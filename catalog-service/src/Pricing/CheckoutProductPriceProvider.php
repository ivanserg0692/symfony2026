<?php

namespace App\Pricing;

use App\Entity\ProductPrice;
use App\Repository\CatalogElementsRepository;
use App\Repository\ProductPriceRepository;

final readonly class CheckoutProductPriceProvider
{
    private const BASE_PRICE_TYPE = "BASE";
    private const SALE_PRICE_TYPE = "SALE";

    public function __construct(
        private CatalogElementsRepository $catalogElementsRepository,
        private ProductPriceRepository $productPriceRepository,
    ) {
    }

    /**
     * @param int[] $productIds
     *
     * @return array<int, CheckoutProductPrice>
     */
    public function getPricesForProducts(array $productIds): array
    {
        $this->ensureProductsExist($productIds);

        $pricesByProduct = [];
        foreach ($this->productPriceRepository->findActivePricesForProducts(
            $productIds,
            [self::BASE_PRICE_TYPE, self::SALE_PRICE_TYPE],
            new \DateTimeImmutable(),
        ) as $price) {
            $product = $price->getProduct();
            $priceType = $price->getPriceType();

            if ($product?->getId() === null || $priceType?->getCode() === null || $price->getPrice() === null) {
                continue;
            }

            $pricesByProduct[$product->getId()][$priceType->getCode()] = $price;
        }

        $checkoutPrices = [];
        foreach ($productIds as $productId) {
            $checkoutPrices[$productId] = $this->createCheckoutPrice($productId, $pricesByProduct[$productId] ?? []);
        }

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

        $unitPrice = $basePrice->getPrice();
        $salePrice = $pricesByType[self::SALE_PRICE_TYPE] ?? null;
        $finalUnitPrice = $salePrice instanceof ProductPrice && $salePrice->getPrice() !== null
            ? min($unitPrice, $salePrice->getPrice())
            : $unitPrice;
        $unitDiscount = max(0, $unitPrice - $finalUnitPrice);

        return new CheckoutProductPrice(
            $productId,
            (string) $unitPrice,
            (string) $unitDiscount,
            (string) $finalUnitPrice,
        );
    }
}
