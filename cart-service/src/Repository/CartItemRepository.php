<?php

namespace App\Repository;

use App\Entity\CartItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function findOneInActiveCartForOwner(int $itemId, int $ownerId): ?CartItem
    {
        return $this->createQueryBuilder("item")
            ->innerJoin("item.cart", "cart")
            ->addSelect("cart")
            ->andWhere("item.id = :itemId")
            ->andWhere("cart.ownerId = :ownerId")
            ->setParameter("itemId", $itemId)
            ->setParameter("ownerId", $ownerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneProductInActiveCartForOwner(int $productId, int $ownerId): ?CartItem
    {
        return $this->createQueryBuilder("item")
            ->innerJoin("item.cart", "cart")
            ->addSelect("cart")
            ->andWhere("item.catalogElementId = :productId")
            ->andWhere("cart.ownerId = :ownerId")
            ->setParameter("productId", $productId)
            ->setParameter("ownerId", $ownerId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
