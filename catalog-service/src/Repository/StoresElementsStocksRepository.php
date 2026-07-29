<?php

namespace App\Repository;

use App\Entity\StoresElementsStocks;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoresElementsStocks>
 */
class StoresElementsStocksRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoresElementsStocks::class);
    }

    public function findOneForProductStoreWithWriteLock(int $productId, int $storeId): ?StoresElementsStocks
    {
        return $this->createQueryBuilder("stock")
            ->addSelect("IDENTITY(stock.element) AS HIDDEN product_id")
            ->addSelect("IDENTITY(stock.store) AS HIDDEN store_id")
            ->andWhere("IDENTITY(stock.element) = :product_id")
            ->andWhere("IDENTITY(stock.store) = :store_id")
            ->setParameter("product_id", $productId)
            ->setParameter("store_id", $storeId)
            ->orderBy("product_id", "ASC")
            ->addOrderBy("store_id", "ASC")
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * @return list<StoresElementsStocks>
     */
    public function findPositiveForProductWithWriteLock(int $productId): array
    {
        return $this->createQueryBuilder("stock")
            ->addSelect("IDENTITY(stock.element) AS HIDDEN product_id")
            ->addSelect("IDENTITY(stock.store) AS HIDDEN store_id")
            ->andWhere("IDENTITY(stock.element) = :product_id")
            ->andWhere("stock.stock > 0")
            ->setParameter("product_id", $productId)
            ->orderBy("product_id", "ASC")
            ->addOrderBy("store_id", "ASC")
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }
}
