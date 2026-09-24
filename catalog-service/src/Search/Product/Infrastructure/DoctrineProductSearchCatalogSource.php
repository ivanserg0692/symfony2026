<?php

namespace App\Search\Product\Infrastructure;

use App\Repository\CatalogElementsRepository;
use App\Search\Product\Port\Output\ProductSearchCatalogSourceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProductSearchCatalogSourceInterface::class)]
final readonly class DoctrineProductSearchCatalogSource implements ProductSearchCatalogSourceInterface
{
    public function __construct(
        private CatalogElementsRepository $catalogElementsRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function countProducts(): int
    {
        return $this->catalogElementsRepository->count([]);
    }

    public function findIdsAfter(int $lastId, int $limit): array
    {
        return $this->catalogElementsRepository->findSearchIndexIdsAfter($lastId, $limit);
    }

    public function loadByIds(array $ids): array
    {
        return $this->catalogElementsRepository->findForSearchIndexingByIds($ids);
    }

    public function releaseLoadedBatch(): void
    {
        $this->entityManager->clear();
    }
}
