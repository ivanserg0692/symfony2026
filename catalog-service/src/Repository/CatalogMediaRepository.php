<?php

namespace App\Repository;

use App\Entity\CatalogMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CatalogMedia> */
class CatalogMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogMedia::class);
    }

    /** A file can have millions of owners: resolve only one indexed row for file delivery. */
    public function findOneByPictureId(string $pictureId): ?CatalogMedia
    {
        return $this->createQueryBuilder('media')
            ->where('media.pictureId = :key')->setParameter('key', $pictureId)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
