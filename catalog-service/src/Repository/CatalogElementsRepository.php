<?php

namespace App\Repository;

use App\Entity\CatalogElements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogElements>
 */
class CatalogElementsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogElements::class);
    }

    public function addStoreRelations(
        QueryBuilder $queryBuilder,
        string $elementAlias = 'c',
        string $storeStockAlias = 'storeStock',
        string $storeAlias = 'store',
    ): QueryBuilder {
        return $queryBuilder
            ->leftJoin(sprintf('%s.storeStocks', $elementAlias), $storeStockAlias)
            ->addSelect($storeStockAlias)
            ->leftJoin(sprintf('%s.store', $storeStockAlias), $storeAlias)
            ->addSelect($storeAlias);
    }

    private function addSectionsRelation(
        QueryBuilder $queryBuilder,
        string $elementAlias = 'c',
        string $sectionAlias = 'section',
    ): QueryBuilder {
        return $queryBuilder
            ->leftJoin(sprintf('%s.sections', $elementAlias), $sectionAlias)
            ->addSelect($sectionAlias);
    }

    /**
     * @return CatalogElements[]
     */
    public function findAllWithStoreStocks(): array
    {
        $queryBuilder = $this->createQueryBuilder('c');

        $this->addStoreRelations($queryBuilder);
        $this->addSectionsRelation($queryBuilder);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findOneWithStoreStocks(int $id): ?CatalogElements
    {
        $queryBuilder = $this->createQueryBuilder('c');

        $this->addStoreRelations($queryBuilder);
        $this->addSectionsRelation($queryBuilder);

        return $queryBuilder
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
