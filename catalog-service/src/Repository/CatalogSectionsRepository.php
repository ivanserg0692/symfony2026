<?php

namespace App\Repository;

use App\Entity\CatalogSections;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogSections>
 */
class CatalogSectionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogSections::class);
    }

    /**
     * @return CatalogSections[]
     */
    public function findActiveForPublicList(): array
    {
        return $this->createQueryBuilder("section")
            ->leftJoin("section.parent", "parent")
            ->addSelect("parent")
            ->andWhere("section.active = :active")
            ->setParameter("active", true)
            ->orderBy("section.sort", "DESC")
            ->addOrderBy("section.id", "ASC")
            ->getQuery()
            ->getResult();
    }

    public function findOneForPublicApi(int $id): ?CatalogSections
    {
        return $this->createQueryBuilder("section")
            ->leftJoin("section.parent", "parent")
            ->addSelect("parent")
            ->andWhere("section.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
