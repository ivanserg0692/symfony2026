<?php

namespace App\Repository;

use App\Entity\ProductPrice;
use App\RoadRunner\Grpc\GrpcHandlerTiming;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductPrice>
 */
class ProductPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private GrpcHandlerTiming $handlerTiming)
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

        $this->handlerTiming->mark('active_prices.query_build_started');
        $query = $this->createActivePricesQueryBuilder($now)
            ->andWhere("product.id IN (:productIds)")
            ->andWhere("priceType.code IN (:priceTypeCodes)")
            ->setParameter("productIds", $productIds)
            ->setParameter("priceTypeCodes", $priceTypeCodes)
            ->getQuery();
        $this->handlerTiming->mark('active_prices.query_built');
        $prices = $query->getResult();
        $this->handlerTiming->mark('active_prices.query_executed_and_hydrated');

        return $prices;
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
