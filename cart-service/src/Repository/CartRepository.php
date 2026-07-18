<?php

namespace App\Repository;

use App\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findActiveForOwner(int $ownerId): ?Cart
    {
        return $this->createOwnerQueryBuilder($ownerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveForOwnerWithItems(int $ownerId): ?Cart
    {
        $queryBuilder = $this->createOwnerQueryBuilder($ownerId);
        $this->addItemsRelation($queryBuilder);

        return $queryBuilder
            ->addOrderBy("item.sort", "ASC")
            ->addOrderBy("item.id", "ASC")
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createOwnerQueryBuilder(int $ownerId): QueryBuilder
    {
        return $this->createQueryBuilder("cart")
            ->andWhere("cart.ownerId = :ownerId")
            ->setParameter("ownerId", $ownerId);
    }

    private function addItemsRelation(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder
            ->leftJoin("cart.items", "item")
            ->addSelect("item");
    }
}
