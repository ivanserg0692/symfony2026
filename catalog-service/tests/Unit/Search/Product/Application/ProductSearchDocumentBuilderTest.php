<?php

namespace App\Tests\Unit\Search\Product\Application;

use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Entity\PriceType;
use App\Entity\ProductPrice;
use App\Entity\Stores;
use App\Entity\StoresElementsStocks;
use App\Search\Product\Application\Dto\Document\ProductSearchPrice;
use App\Search\Product\Application\Dto\Document\ProductSearchSection;
use App\Search\Product\Application\Dto\Document\ProductSearchStock;
use App\Search\Product\Application\ProductSearchDocumentBuilder;
use App\Search\Product\Port\Output\Document\ProductSearchIndexDocumentInterface;
use PHPUnit\Framework\TestCase;

final class ProductSearchDocumentBuilderTest extends TestCase
{
    public function testBuildsDenormalizedDocumentWithStableRelationOrdering(): void
    {
        $element = (new CatalogElements())
            ->setName("Test product")
            ->setSlug("test-product")
            ->setDescription("Description")
            ->setPictureId("picture-1")
            ->setActive(true)
            ->setCreatedBy(7)
            ->setCreatedAt(new \DateTimeImmutable("2026-08-31T12:30:00+00:00"))
            ->setSort(90);
        $this->setPrivateProperty($element, "id", 42);
        $element->getProduct()?->setId(142);

        $element->addSection((new CatalogSections())->setId(20)->setName("Second")->setSlug("second")->setActive(true)->setSort(20));
        $element->addSection((new CatalogSections())->setId(10)->setName("First")->setSlug("first")->setActive(true)->setSort(10));

        $priceType = (new PriceType())->setCode("retail")->setName("Retail")->setActive(true);
        $this->setPrivateProperty($priceType, "id", 3);
        $price = (new ProductPrice())
            ->setPriceType($priceType)
            ->setPrice(129900)
            ->setCurrency("RUB")
            ->setActive(true)
            ->setValidFrom(new \DateTimeImmutable("2026-09-01T00:00:00+00:00"));
        $this->setPrivateProperty($price, "id", 8);
        $element->addProductPrice($price);

        $store = (new Stores())->setId(5)->setName("Main store")->setSlug("main-store")->setActive(true);
        $element->addStoreStock((new StoresElementsStocks())->setStore($store)->setStock(6));

        $document = (new ProductSearchDocumentBuilder())->build($element);
        $source = $document->toArray();

        self::assertInstanceOf(ProductSearchIndexDocumentInterface::class, $document);
        self::assertSame(42, $document->id);
        self::assertSame(42, $document->getId());
        self::assertSame(142, $document->productId);
        self::assertContainsOnlyInstancesOf(ProductSearchSection::class, $document->sections);
        self::assertContainsOnlyInstancesOf(ProductSearchPrice::class, $document->prices);
        self::assertContainsOnlyInstancesOf(ProductSearchStock::class, $document->stocks);
        self::assertSame(142, $source["product_id"]);
        self::assertSame([10, 20], $source["section_ids"]);
        self::assertSame("retail", $source["prices"][0]["type_code"]);
        self::assertSame(129900, $source["prices"][0]["amount"]);
        self::assertSame(6, $source["total_stock"]);
        self::assertTrue($source["available"]);
        self::assertSame(5, $source["stocks"][0]["store_id"]);
        self::assertSame("2026-08-31T12:30:00+00:00", $source["created_at"]);
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setValue($object, $value);
    }
}
