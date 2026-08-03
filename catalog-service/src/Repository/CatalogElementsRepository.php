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
     * @return int[]
     */
    public function findPageIds(?int $sectionId, ?bool $active, int $page, int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder("element")
            ->select("element.id AS id")
            ->innerJoin("element.product", "product");

        $this->applyListFilters($queryBuilder, $sectionId, $active);

        $rows = $queryBuilder
            ->orderBy("element.sort", "DESC")
            ->addOrderBy("element.id", "ASC")
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map("intval", array_column($rows, "id"));
    }

    /**
     * @param int[] $ids
     *
     * @return CatalogElements[]
     */
    public function findListByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder("element");

        $this->addProductRelation($queryBuilder);
        $this->addProductPriceRelations($queryBuilder);
        $this->addStockRowsForTotal($queryBuilder);

        $elements = $queryBuilder
            ->andWhere("element.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()->getResult();

        return $this->sortElementsByIds($elements, $ids);
    }

    public function countMatchingListFilters(?int $sectionId, ?bool $active): int
    {
        $queryBuilder = $this->createQueryBuilder("element")
            ->select("COUNT(element.id)")
            ->innerJoin("element.product", "product");

        $this->applyListFilters($queryBuilder, $sectionId, $active);

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForPublicApi(int $id): ?CatalogElements
    {
        $queryBuilder = $this->createQueryBuilder("element");

        $this->addProductRelation($queryBuilder);
        $this->addSectionsRelation($queryBuilder);
        $this->addProductPriceRelations($queryBuilder);
        $this->addStockRowsForTotal($queryBuilder);

        return $queryBuilder
            ->andWhere("element.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $ids
     *
     * @return int[]
     */
    public function findExistingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->createQueryBuilder("element")
            ->select("element.id AS id")
            ->andWhere("element.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getScalarResult();

        return array_map("intval", array_column($rows, "id"));
    }

    public function existsById(int $id): bool
    {
        return (bool) $this->createQueryBuilder("element")
            ->select("1")
            ->andWhere("element.id = :id")
            ->setParameter("id", $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForInventoryCheck(int $id): ?CatalogElements
    {
        $queryBuilder = $this->createQueryBuilder("element");

        $this->addProductRelation($queryBuilder);
        $this->addStoreRelations($queryBuilder);

        return $queryBuilder
            ->andWhere("element.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForInventorySnapshot(int $id): ?CatalogElements
    {
        $queryBuilder = $this->createQueryBuilder("element");

        $this->addProductRelation($queryBuilder);

        return $queryBuilder
            ->andWhere("element.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function addProductRelation(
        QueryBuilder $queryBuilder,
        string $elementAlias = "element",
        string $productAlias = "product",
    ): QueryBuilder {
        return $queryBuilder
            ->innerJoin(sprintf("%s.product", $elementAlias), $productAlias)
            ->addSelect($productAlias);
    }

    private function addSectionsRelation(
        QueryBuilder $queryBuilder,
        string $productAlias = "product",
        string $sectionAlias = "section",
    ): QueryBuilder {
        return $queryBuilder
            ->leftJoin(sprintf("%s.sections", $productAlias), $sectionAlias)
            ->addSelect($sectionAlias)
            ->leftJoin(sprintf("%s.parent", $sectionAlias), "sectionParent")
            ->addSelect("sectionParent");
    }

    private function applyListFilters(QueryBuilder $queryBuilder, ?int $sectionId, ?bool $active): QueryBuilder
    {
        if ($sectionId !== null) {
            $queryBuilder
                ->innerJoin("product.sections", "filterSection")
                ->andWhere("filterSection.id = :sectionId")
                ->setParameter("sectionId", $sectionId);
        }

        if ($active !== null) {
            $queryBuilder
                ->andWhere("product.active = :active")
                ->setParameter("active", $active);
        }

        return $queryBuilder;
    }

    /**
     * @param CatalogElements[] $elements
     * @param int[] $ids
     *
     * @return CatalogElements[]
     */
    private function sortElementsByIds(array $elements, array $ids): array
    {
        $elementsById = [];

        foreach ($elements as $element) {
            $elementsById[$element->getId()] = $element;
        }

        $orderedElements = [];

        foreach ($ids as $id) {
            if (isset($elementsById[$id])) {
                $orderedElements[] = $elementsById[$id];
            }
        }

        return $orderedElements;
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
