<?php

namespace App\Repository;

use App\Entity\CatalogSections;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogSections>
 */
class CatalogSectionsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CatalogElementsRepository $catalogElementsRepository,
    ) {
        parent::__construct($registry, CatalogSections::class);
    }

    /**
     * @return CatalogSections[]
     */
    public function findAllWithCatalogElements(): array
    {
        $queryBuilder = $this->createQueryBuilder('section')
            ->leftJoin('section.catalogElements', 'element')
            ->addSelect('element');

        $this->catalogElementsRepository->addStoreRelations($queryBuilder, 'element');

        return $queryBuilder
            ->orderBy('section.leftMargin', 'ASC')
            ->addOrderBy('section.sort', 'DESC')
            ->addOrderBy('element.sort', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
