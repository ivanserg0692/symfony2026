<?php

namespace App\Repository;

use App\Entity\ProductSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductSnapshot>
 */
class ProductSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductSnapshot::class);
    }

    /**
     * @return int[]
     */
    public function findPageIds(?int $originalProductId, string $sort, string $direction, int $page, int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder("snapshot")
            ->select("snapshot.id AS id")
            ->innerJoin("snapshot.product", "product");

        $this->applyListFilters($queryBuilder, $originalProductId);
        $this->applySorting($queryBuilder, $sort, $direction);

        $rows = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map("intval", array_column($rows, "id"));
    }

    /**
     * @param int[] $ids
     *
     * @return ProductSnapshot[]
     */
    public function findListByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder("snapshot");

        $this->addSnapshotRelations($queryBuilder);

        $snapshots = $queryBuilder
            ->andWhere("snapshot.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getResult();

        return $this->sortSnapshotsByIds($snapshots, $ids);
    }

    public function countMatchingListFilters(?int $originalProductId): int
    {
        $queryBuilder = $this->createQueryBuilder("snapshot")
            ->select("COUNT(snapshot.id)");

        $this->applyListFilters($queryBuilder, $originalProductId);

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForPublicApi(int $id): ?ProductSnapshot
    {
        $queryBuilder = $this->createQueryBuilder("snapshot");

        $this->addSnapshotRelations($queryBuilder);

        return $queryBuilder
            ->andWhere("snapshot.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function addSnapshotRelations(
        QueryBuilder $queryBuilder,
        string $snapshotAlias = "snapshot",
        string $productAlias = "product",
        string $productCatalogElementAlias = "productCatalogElement",
        string $originalProductAlias = "originalProduct",
        string $originalProductModelAlias = "originalProductModel",
        string $originalProductCatalogElementAlias = "originalProductCatalogElement",
    ): QueryBuilder {
        return $queryBuilder
            ->innerJoin(sprintf("%s.product", $snapshotAlias), $productAlias)
            ->addSelect($productAlias)
            ->leftJoin(sprintf("%s.catalogElement", $productAlias), $productCatalogElementAlias)
            ->addSelect($productCatalogElementAlias)
            ->innerJoin(sprintf("%s.originalProduct", $snapshotAlias), $originalProductAlias)
            ->addSelect($originalProductAlias)
            ->innerJoin(sprintf("%s.product", $originalProductAlias), $originalProductModelAlias)
            ->addSelect($originalProductModelAlias)
            ->leftJoin(sprintf("%s.catalogElement", $originalProductModelAlias), $originalProductCatalogElementAlias)
            ->addSelect($originalProductCatalogElementAlias);
    }

    private function applyListFilters(QueryBuilder $queryBuilder, ?int $originalProductId): QueryBuilder
    {
        if ($originalProductId !== null) {
            $queryBuilder
                ->andWhere("IDENTITY(snapshot.originalProduct) = :originalProductId")
                ->setParameter("originalProductId", $originalProductId);
        }

        return $queryBuilder;
    }

    private function applySorting(QueryBuilder $queryBuilder, string $sort, string $direction): QueryBuilder
    {
        $direction = strtoupper($direction) === "DESC" ? "DESC" : "ASC";

        if ($sort === "createdAt") {
            return $queryBuilder
                ->orderBy("product.createdAt", $direction)
                ->addOrderBy("snapshot.id", "ASC");
        }

        return $queryBuilder->orderBy("snapshot.id", $direction);
    }

    /**
     * @param ProductSnapshot[] $snapshots
     * @param int[]             $ids
     *
     * @return ProductSnapshot[]
     */
    private function sortSnapshotsByIds(array $snapshots, array $ids): array
    {
        $snapshotsById = [];

        foreach ($snapshots as $snapshot) {
            $snapshotsById[$snapshot->getId()] = $snapshot;
        }

        $orderedSnapshots = [];

        foreach ($ids as $id) {
            if (isset($snapshotsById[$id])) {
                $orderedSnapshots[] = $snapshotsById[$id];
            }
        }

        return $orderedSnapshots;
    }
}
