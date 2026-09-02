<?php

namespace App\Tests\Unit\Search\Product\Infrastructure;

use App\Entity\CatalogElements;
use App\Repository\CatalogElementsRepository;
use App\Search\Product\Infrastructure\DoctrineProductSearchCatalogSource;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DoctrineProductSearchCatalogSourceTest extends TestCase
{
    public function testDelegatesBoundedReadsAndReleasesDoctrineState(): void
    {
        $element = new CatalogElements();
        $repository = $this->createMock(CatalogElementsRepository::class);
        $repository->expects(self::once())->method("count")->with([])->willReturn(1);
        $repository->expects(self::once())->method("findSearchIndexIdsAfter")->with(10, 25)->willReturn([11]);
        $repository->expects(self::once())->method("findForSearchIndexingByIds")->with([11])->willReturn([$element]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method("clear");

        $source = new DoctrineProductSearchCatalogSource($repository, $entityManager);

        self::assertSame(1, $source->countProducts());
        self::assertSame([11], $source->findIdsAfter(10, 25));
        self::assertSame([$element], $source->loadByIds([11]));
        $source->releaseLoadedBatch();
    }
}
