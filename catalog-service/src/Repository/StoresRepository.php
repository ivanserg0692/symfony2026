<?php

namespace App\Repository;

use App\Entity\Stores;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stores>
 */
class StoresRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stores::class);
    }

    /**
     * @return Stores[]
     */
    public function findActiveForPublicList(): array
    {
        return $this->createQueryBuilder("store")
            ->andWhere("store.active = :active")
            ->setParameter("active", true)
            ->orderBy("store.id", "ASC")
            ->getQuery()
            ->getResult();
    }

    public function existsById(int $id): bool
    {
        return (bool) $this->createQueryBuilder("store")
            ->select("1")
            ->andWhere("store.id = :id")
            ->setParameter("id", $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForPublicApi(int $id): ?Stores
    {
        return $this->find($id);
    }
}
