<?php

namespace App\Search\Product\Application;

use App\Entity\CatalogElements;
use App\Search\Product\Application\Dto\Document\ProductSearchDocument;
use App\Search\Product\Application\Dto\Document\ProductSearchPrice;
use App\Search\Product\Application\Dto\Document\ProductSearchSection;
use App\Search\Product\Application\Dto\Document\ProductSearchStock;

final class ProductSearchDocumentBuilder
{
    public function build(CatalogElements $element): ProductSearchDocument
    {
        $id = $element->getId();
        $productId = $element->getProduct()?->getId();

        if ($id === null || $productId === null) {
            throw new \LogicException("Only persisted catalog elements can be indexed.");
        }

        $sections = [];
        foreach ($element->getSections() as $section) {
            $sectionId = $section->getId();
            if ($sectionId === null) {
                continue;
            }

            $sections[] = new ProductSearchSection(
                id: $sectionId,
                name: $section->getName(),
                slug: $section->getSlug(),
                active: $section->isActive(),
                parentId: $section->getParentId(),
                level: $section->getLevel(),
                sort: $section->getSort(),
            );
        }
        usort($sections, static fn(ProductSearchSection $left, ProductSearchSection $right): int => $left->id <=> $right->id);

        $prices = [];
        foreach ($element->getProductPrices() as $price) {
            $priceType = $price->getPriceType();
            $priceId = $price->getId();
            if ($priceType === null || $priceId === null) {
                continue;
            }

            $prices[] = new ProductSearchPrice(
                id: $priceId,
                typeId: $priceType->getId(),
                typeCode: $priceType->getCode(),
                typeName: $priceType->getName(),
                typeActive: $priceType->isActive(),
                amount: $price->getPrice(),
                currency: $price->getCurrency(),
                active: $price->isActive(),
                validFrom: $price->getValidFrom(),
                validTo: $price->getValidTo(),
            );
        }
        usort($prices, static fn(ProductSearchPrice $left, ProductSearchPrice $right): int => [$left->typeId, $left->id] <=> [$right->typeId, $right->id]);

        $stocks = [];
        foreach ($element->getStoreStocks() as $storeStock) {
            $store = $storeStock->getStore();
            if ($store === null || $store->getId() === null) {
                continue;
            }

            $quantity = $storeStock->getStock() ?? 0;
            $stocks[] = new ProductSearchStock(
                storeId: $store->getId(),
                storeName: $store->getName(),
                storeSlug: $store->getSlug(),
                storeActive: $store->isActive(),
                quantity: $quantity,
            );
        }
        usort($stocks, static fn(ProductSearchStock $left, ProductSearchStock $right): int => $left->storeId <=> $right->storeId);

        return new ProductSearchDocument(
            id: $id,
            productId: $productId,
            name: $element->getName(),
            slug: $element->getSlug(),
            description: $element->getDescription(),
            pictureId: $element->getPictureId(),
            active: $element->isActive(),
            sort: $element->getSort(),
            createdAt: $element->getCreatedAt(),
            sections: $sections,
            prices: $prices,
            stocks: $stocks,
        );
    }
}
