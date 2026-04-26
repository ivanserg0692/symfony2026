<?php

namespace App\Repository;

use App\Entity\NewsStatus;
use App\Entity\User;
use App\Enum\NewsStatusCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsStatus>
 */
class NewsStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsStatus::class);
    }

    public function createAvailableForUserQueryBuilder(?User $user): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('status')
            ->orderBy('status.name', 'ASC');

        if (!$user?->isAdmin()) {
            $queryBuilder
                ->andWhere('status.code IN (:codes)')
                ->setParameter('codes', [
                    NewsStatusCode::DRAFTED,
                    NewsStatusCode::ON_MODERATION,
                ]);
        }

        return $queryBuilder;
    }
}
