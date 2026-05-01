<?php

namespace App\Repository;

use App\Dto\Sorting\ListQueryDto;
use App\Entity\Notifications;
use App\Entity\User;
use App\Repository\Services\ListQueryNormalizer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notifications>
 */
class NotificationsRepository extends ServiceEntityRepository
{
    private const ROOT_ALIAS = 'notifications';
    public const DEFAULT_SORT = 'createdAt';
    public const ALLOWED_SORTS = ['id', 'createdAt', 'readAt'];

    public function __construct(
        ManagerRegistry $registry,
        private readonly ListQueryNormalizer $listQueryNormalizer,
    ) {
        parent::__construct($registry, Notifications::class);
    }

    public function save(Notifications $notification): void
    {
        $this->getEntityManager()->persist($notification);
    }

    public function createListQueryBuilder(ListQueryDto $query, User $user): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder(self::ROOT_ALIAS);
        $queryBuilder->andWhere(self::ROOT_ALIAS . '.recipient = :recipient');
        $queryBuilder->setParameter('recipient', $user);

        return $queryBuilder->orderBy(
            self::ROOT_ALIAS . '.' . $this->listQueryNormalizer->normalizeSort(
                $query->sort,
                self::ALLOWED_SORTS,
                self::DEFAULT_SORT,
            ),
            $this->listQueryNormalizer->normalizeDirection($query->direction)
        );
    }

    public function deleteByRecipient(User $recipient): int
    {
        return $this->createQueryBuilder(self::ROOT_ALIAS)
            ->delete()
            ->andWhere(self::ROOT_ALIAS . '.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->execute();
    }
}
