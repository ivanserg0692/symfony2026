<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Doctrine\IncrementalIndexing;

use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Entity\PriceType;
use App\Entity\ProductPrice;
use App\Repository\CatalogElementsRepository;
use App\Repository\CatalogSectionsRepository;
use App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing\RelationalChangeImpact\CatalogSectionSearchChangeImpactResolver;
use App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing\RelationalChangeImpact\PriceTypeSearchChangeImpactResolver;
use PHPUnit\Framework\TestCase;

final class RelationalChangeImpactTest extends TestCase
{
    public function testPriceTypeMetadataChangesResolveAllRelatedCatalogElements(): void
    {
        $type = new PriceType();
        $first = $this->element(10);
        $second = $this->element(20);
        (new ProductPrice())->setProduct($first)->setPriceType($type);
        (new ProductPrice())->setProduct($second)->setPriceType($type);
        $resolver = new PriceTypeSearchChangeImpactResolver();

        foreach (['sort' => [100, 0], 'description' => ['Old', null]] as $field => $change) {
            self::assertTrue($resolver->hasIndexedChanges([$field]));
            self::assertSame([10, 20], $resolver->resolveEntityChange($type, [$field => $change], false));
        }

        self::assertFalse($resolver->hasIndexedChanges(['updatedAt']));
        foreach (['code', 'name', 'active'] as $field) {
            self::assertTrue($resolver->hasIndexedChanges([$field]));
        }
    }

    public function testSectionMetadataChangesResolveDirectProductsWithoutTraversingDescendants(): void
    {
        $section = (new CatalogSections())->setId(3);
        $first = $this->element(10);
        $second = $this->element(20);
        $products = [$first->getProduct(), $second->getProduct()];
        $sections = $this->createMock(CatalogSectionsRepository::class);
        $elements = $this->createMock(CatalogElementsRepository::class);
        $sections->expects(self::exactly(2))->method('collectProducts')
            ->with($section, false)->willReturn($products);
        $elements->expects(self::exactly(2))->method('findByProducts')
            ->with($products)->willReturn([$first, $second]);
        $resolver = new CatalogSectionSearchChangeImpactResolver($sections, $elements);

        foreach (['description', 'pictureId'] as $field) {
            self::assertTrue($resolver->hasIndexedChanges([$field]));
            self::assertSame([10, 20], $resolver->resolveEntityChange($section, [$field => ['Old', null]], false));
        }

        self::assertFalse($resolver->hasIndexedChanges(['updatedAt']));
        foreach (['name', 'slug', 'active', 'level', 'parent', 'leftMargin', 'rightMargin', 'sort'] as $field) {
            self::assertTrue($resolver->hasIndexedChanges([$field]));
        }
    }

    private function element(int $id): CatalogElements
    {
        $element = new CatalogElements();
        (new \ReflectionProperty($element, 'id'))->setValue($element, $id);

        return $element;
    }
}
