<?php

namespace App\Repository;

use App\Dto\Sorting\ListQueryDto;
use App\Entity\Notifications;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notifications>
 */
class NotificationsRepository extends ServiceEntityRepository
{
    private const ROOT_ALIAS = 'notifications';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notifications::class);
    }


    public function createListQueryBuilder(ListQueryDto $query, User $user): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder(self::ROOT_ALIAS);
        $queryBuilder->andWhere(self::ROOT_ALIAS . '.user = :user');
        $queryBuilder->setParameter('user', $user);

        return $queryBuilder->orderBy(
            self::ROOT_ALIAS . '.' . $this->normalizeSort($query->sort),
            $this->normalizeDirection($query->direction)
        );
    }
}
