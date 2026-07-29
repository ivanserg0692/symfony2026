<?php

namespace App\Repository;

use App\Entity\ProductPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductPrice>
 */
class ProductPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductPrice::class);
    }

    /**
     * @param int[] $productIds
     * @param string[] $priceTypeCodes
     *
     * @return list<ProductPrice>
     */
    public function findActivePricesForProducts(array $productIds, array $priceTypeCodes, \DateTimeImmutable $now): array
    {
        if ($productIds === [] || $priceTypeCodes === []) {
            return [];
        }

        return $this->createActivePricesQueryBuilder($now)
            ->andWhere("product.id IN (:productIds)")
            ->andWhere("priceType.code IN (:priceTypeCodes)")
            ->setParameter("productIds", $productIds)
            ->setParameter("priceTypeCodes", $priceTypeCodes)
            ->getQuery()
            ->getResult();
    }

    private function createActivePricesQueryBuilder(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder("productPrice")
            ->innerJoin("productPrice.product", "product")
            ->addSelect("product")
            ->innerJoin("productPrice.priceType", "priceType")
            ->addSelect("priceType")
            ->andWhere("productPrice.active = true")
            ->andWhere("priceType.active = true")
            ->andWhere("productPrice.validFrom IS NULL OR productPrice.validFrom <= :now")
            ->andWhere("productPrice.validTo IS NULL OR productPrice.validTo > :now")
            ->setParameter("now", $now);
    }
}
