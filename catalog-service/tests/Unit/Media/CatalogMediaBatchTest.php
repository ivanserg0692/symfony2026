<?php

namespace App\Tests\Unit\Media;

use App\Media\Infrastructure\CatalogPictureResponseFactory;
use App\Search\Product\Infrastructure\CatalogResponseFactory;
use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Repository\CatalogElementsRepository;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Doctrine\DoctrineCatalogReader;
use App\Tests\Support\CatalogMediaFactory;
use PHPUnit\Framework\TestCase;

final class CatalogMediaBatchTest extends TestCase
{
    public function testPageUsesJoinedPicturesWithIndependentMetadataForSharedFile(): void
    {
        $elements = [];
        for ($i = 1; $i <= 100; ++$i) {
            $picture = CatalogMediaFactory::create('chair', 'Chair '.$i);
            $element = (new CatalogElements())->setName('Chair')->setPicture($picture);
            $element->getProduct()->setId($i);
            $elements[] = $element;
        }
        $repository = $this->createMock(CatalogElementsRepository::class);
        $repository->method('findPageIds')->willReturn(range(1, 100));
        $repository->method('findListByIds')->willReturn($elements);
        $page = (new DoctrineCatalogReader($repository, new CatalogResponseFactory(new CatalogPictureResponseFactory('https://shop.example/media'))))
            ->findPage(new CatalogListCriteria(null, null, 1, 100, false));
        self::assertCount(100, $page->items);
        self::assertSame($page->items[0]->picture->fullUrl, $page->items[99]->picture->fullUrl);
        self::assertSame('Chair 1', $page->items[0]->picture->alt);
        self::assertSame('Chair 100', $page->items[99]->picture->alt);
    }

    public function testDetailUsesJoinedProductAndSectionPictures(): void
    {
        $productMedia = CatalogMediaFactory::create('chair', 'Product');
        $sectionMedia = CatalogMediaFactory::create('chair', 'Section');
        $element = (new CatalogElements())->setPicture($productMedia)
            ->addSection((new CatalogSections())->setId(2)->setPicture($sectionMedia))
            ->addSection((new CatalogSections())->setId(3));
        $element->getProduct()->setId(1);
        $repository = $this->createMock(CatalogElementsRepository::class);
        $repository->method('findOneForPublicApi')->willReturn($element);
        $item = (new DoctrineCatalogReader($repository, new CatalogResponseFactory(new CatalogPictureResponseFactory('https://shop.example/media'))))->findById(1);
        self::assertSame('Product', $item->picture->alt);
        self::assertSame('Section', $item->sections[0]->picture->alt);
        self::assertNull($item->sections[1]->picture);
    }

}
