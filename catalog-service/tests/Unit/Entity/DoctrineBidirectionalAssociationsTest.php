<?php

namespace App\Tests\Unit\Entity;

use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Entity\PriceType;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Stores;
use App\Entity\StoresElementsStocks;
use PHPUnit\Framework\TestCase;

class DoctrineBidirectionalAssociationsTest extends TestCase
{
    public function testStockKeepsStoresSynchronizedWhenStoreChanges(): void
    {
        $previousStore = new Stores();
        $newStore = new Stores();
        $stock = new StoresElementsStocks();

        $stock->setStore($previousStore);
        $stock->setStore($newStore);
        $stock->setStore($newStore);

        self::assertFalse($previousStore->getElementStocks()->contains($stock));
        self::assertTrue($newStore->getElementStocks()->contains($stock));

        $stock->setStore(null);

        self::assertFalse($newStore->getElementStocks()->contains($stock));
    }

    public function testStockKeepsCatalogElementsSynchronizedWhenElementChanges(): void
    {
        $previousElement = new CatalogElements();
        $newElement = new CatalogElements();
        $stock = new StoresElementsStocks();

        $stock->setElement($previousElement);
        $stock->setElement($newElement);
        $stock->setElement($newElement);

        self::assertFalse($previousElement->getStoreStocks()->contains($stock));
        self::assertTrue($newElement->getStoreStocks()->contains($stock));

        $stock->setElement(null);

        self::assertFalse($newElement->getStoreStocks()->contains($stock));
    }

    public function testPriceKeepsPriceTypesSynchronizedWhenPriceTypeChanges(): void
    {
        $previousPriceType = new PriceType();
        $newPriceType = new PriceType();
        $price = new ProductPrice();

        $price->setPriceType($previousPriceType);
        $price->setPriceType($newPriceType);
        $price->setPriceType($newPriceType);

        self::assertFalse($previousPriceType->getProductPrices()->contains($price));
        self::assertTrue($newPriceType->getProductPrices()->contains($price));

        $price->setPriceType(null);

        self::assertFalse($newPriceType->getProductPrices()->contains($price));
    }

    public function testPriceKeepsCatalogElementsSynchronizedWhenProductChanges(): void
    {
        $previousElement = new CatalogElements();
        $newElement = new CatalogElements();
        $price = new ProductPrice();

        $price->setProduct($previousElement);
        $price->setProduct($newElement);
        $price->setProduct($newElement);

        self::assertFalse($previousElement->getProductPrices()->contains($price));
        self::assertTrue($newElement->getProductPrices()->contains($price));

        $price->setProduct(null);

        self::assertFalse($newElement->getProductPrices()->contains($price));
    }

    public function testSectionKeepsParentsSynchronizedWhenParentChanges(): void
    {
        $previousParent = new CatalogSections();
        $newParent = new CatalogSections();
        $section = new CatalogSections();

        $section->setParent($previousParent);
        $section->setParent($newParent);
        $section->setParent($newParent);

        self::assertFalse($previousParent->getCatalogSections()->contains($section));
        self::assertTrue($newParent->getCatalogSections()->contains($section));

        $section->setParent(null);

        self::assertFalse($newParent->getCatalogSections()->contains($section));
    }

    public function testSectionKeepsProductsSynchronizedFromInverseSide(): void
    {
        $section = new CatalogSections();
        $product = new Product();

        $section->addProduct($product);
        $section->addProduct($product);

        self::assertTrue($section->getProducts()->contains($product));
        self::assertTrue($product->getSections()->contains($section));

        $section->removeProduct($product);

        self::assertFalse($section->getProducts()->contains($product));
        self::assertFalse($product->getSections()->contains($section));
    }
}
