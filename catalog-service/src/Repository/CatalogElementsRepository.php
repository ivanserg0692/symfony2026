<?php

namespace App\Repository;

use App\Entity\CatalogElements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
        string $elementAlias = "element",
        string $storeStockAlias = "storeStock",
        string $storeAlias = "store",
    ): QueryBuilder {
        return $queryBuilder
            ->leftJoin(sprintf("%s.storeStocks", $elementAlias), $storeStockAlias)
            ->addSelect($storeStockAlias)
            ->leftJoin(sprintf("%s.store", $storeStockAlias), $storeAlias)
            ->addSelect($storeAlias);
    }

    /**
     * @return Paginator<CatalogElements>
     */
    public function findPaginatedForPublicApi(?int $sectionId, ?bool $active, int $page, int $limit): Paginator
    {
        $queryBuilder = $this->createQueryBuilder("element");

        $this->addProductPriceRelations($queryBuilder);
        $this->addStockRowsForTotal($queryBuilder);

        if ($sectionId !== null) {
            $queryBuilder
                ->innerJoin("element.sections", "filterSection")
                ->andWhere("filterSection.id = :sectionId")
                ->setParameter("sectionId", $sectionId);
        }

        if ($active !== null) {
            $queryBuilder
                ->andWhere("element.active = :active")
                ->setParameter("active", $active);
        }

        $queryBuilder
            ->orderBy("element.sort", "DESC")
            ->addOrderBy("element.id", "ASC")
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($queryBuilder->getQuery(), true);
    }

    public function findOneForPublicApi(int $id): ?CatalogElements
    {
        $queryBuilder = $this->createQueryBuilder("element");

        $this->addSectionsRelation($queryBuilder);
        $this->addProductPriceRelations($queryBuilder);
        $this->addStockRowsForTotal($queryBuilder);

        return $queryBuilder
            ->andWhere("element.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function addSectionsRelation(
        QueryBuilder $queryBuilder,
        string $elementAlias = "element",
        string $sectionAlias = "section",
    ): QueryBuilder {
        return $queryBuilder
            ->leftJoin(sprintf("%s.sections", $elementAlias), $sectionAlias)
            ->addSelect($sectionAlias)
            ->leftJoin(sprintf("%s.parent", $sectionAlias), "sectionParent")
            ->addSelect("sectionParent");
    }

    private function addProductPriceRelations(
        QueryBuilder $queryBuilder,
        string $elementAlias = "element",
        string $priceAlias = "productPrice",
        string $priceTypeAlias = "priceType",
    ): QueryBuilder {
        return $queryBuilder
            ->leftJoin(sprintf("%s.productPrices", $elementAlias), $priceAlias)
            ->addSelect($priceAlias)
            ->leftJoin(sprintf("%s.priceType", $priceAlias), $priceTypeAlias)
            ->addSelect($priceTypeAlias);
    }

    private function addStockRowsForTotal(
        QueryBuilder $queryBuilder,
        string $elementAlias = "element",
        string $storeStockAlias = "storeStock",
    ): QueryBuilder {
        return $queryBuilder
            ->leftJoin(sprintf("%s.storeStocks", $elementAlias), $storeStockAlias)
            ->addSelect($storeStockAlias);
    }
}
