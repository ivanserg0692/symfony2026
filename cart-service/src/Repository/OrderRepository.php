<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return list<int>
     */
    public function findPageIdsForOwner(int $ownerId, int $page, int $limit): array
    {
        $rows = $this->createOwnerQueryBuilder($ownerId)
            ->select("orderEntity.id")
            ->orderBy("orderEntity.createdAt", "DESC")
            ->addOrderBy("orderEntity.id", "DESC")
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map("intval", $rows);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Order>
     */
    public function findListByIdsForOwner(array $ids, int $ownerId): array
    {
        if ($ids === []) {
            return [];
        }

        $orders = $this->createOwnerQueryBuilder($ownerId)
            ->andWhere("orderEntity.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getResult();

        $ordersById = [];

        foreach ($orders as $order) {
            $ordersById[$order->getId()] = $order;
        }

        $sortedOrders = [];

        foreach ($ids as $id) {
            if (isset($ordersById[$id])) {
                $sortedOrders[] = $ordersById[$id];
            }
        }

        return $sortedOrders;
    }

    public function countForOwner(int $ownerId): int
    {
        return (int) $this->createOwnerQueryBuilder($ownerId)
            ->select("COUNT(orderEntity.id)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForOwnerWithItems(int $orderId, int $ownerId): ?Order
    {
        $queryBuilder = $this->createOwnerQueryBuilder($ownerId);
        $this->addItemsRelation($queryBuilder);

        return $queryBuilder
            ->andWhere("orderEntity.id = :orderId")
            ->setParameter("orderId", $orderId)
            ->addOrderBy("item.sort", "ASC")
            ->addOrderBy("item.id", "ASC")
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createOwnerQueryBuilder(int $ownerId): QueryBuilder
    {
        return $this->createQueryBuilder("orderEntity")
            ->andWhere("orderEntity.ownerId = :ownerId")
            ->setParameter("ownerId", $ownerId);
    }

    private function addItemsRelation(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder
            ->leftJoin("orderEntity.items", "item")
            ->addSelect("item");
    }
}
