<?php

namespace App\Repository;

use App\Dto\Sorting\ListQueryDto;
use App\Entity\News;
use App\Entity\User;
use App\Enum\NewsStatusCode;
use App\Repository\Services\ListQueryNormalizer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 */
class NewsRepository extends ServiceEntityRepository
{
    private const ROOT_ALIAS = 'news';
    private const CREATED_BY_ASSOCIATION = 'createdBy';
    private const STATUS_ASSOCIATION = 'status';
    public const DEFAULT_SORT = 'createdAt';
    public const ALLOWED_SORTS = ['id', 'slug', 'createdAt'];
    private const CREATED_BY_ALIAS = self::CREATED_BY_ASSOCIATION;
    private const STATUS_ALIAS = 'status';

    public function __construct(
        ManagerRegistry $registry,
        private readonly ListQueryNormalizer $listQueryNormalizer,
    ) {
        parent::__construct($registry, News::class);
    }

    public function createListQueryBuilder(ListQueryDto $query, ?User $user): QueryBuilder
    {
        $queryBuilder = $this->createVisibleQueryBuilder($user);

        return $queryBuilder->orderBy(
            self::ROOT_ALIAS . '.' . $this->listQueryNormalizer->normalizeSort(
                $query->sort,
                self::ALLOWED_SORTS,
                self::DEFAULT_SORT,
            ),
            $this->listQueryNormalizer->normalizeDirection($query->direction)
        );
    }

    public function createVisibleQueryBuilder(?User $user): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder(self::ROOT_ALIAS);

        $this->applyVisibility($queryBuilder, $user);

        return $queryBuilder;
    }

    public function ensureListRelations(QueryBuilder $queryBuilder): QueryBuilder
    {
        $this->ensureJoinAlias($queryBuilder, self::CREATED_BY_ASSOCIATION);
        $this->ensureJoinAlias($queryBuilder, self::STATUS_ASSOCIATION);

        $queryBuilder
            ->addSelect(self::CREATED_BY_ALIAS)
            ->addSelect(self::STATUS_ALIAS);

        return $queryBuilder;
    }

    public function applyVisibility(QueryBuilder $queryBuilder, ?User $user): QueryBuilder
    {
        $this->ensureListRelations($queryBuilder);

        if (!$user instanceof User) {
            return $queryBuilder
                ->andWhere(self::STATUS_ALIAS . '.code = :publicStatus')
                ->setParameter('publicStatus', NewsStatusCode::PUBLIC);
        }

        if ($user->isAdmin()) {
            return $queryBuilder;
        }

        return $queryBuilder
            ->andWhere(sprintf('(%s.code IN (:visibleStatuses) OR %s = :user)', self::STATUS_ALIAS, self::CREATED_BY_ALIAS))
            ->setParameter('visibleStatuses', [NewsStatusCode::PUBLIC, NewsStatusCode::INTERNAL])
            ->setParameter('user', $user);
    }

    private function ensureJoinAlias(QueryBuilder $queryBuilder, string $association): void
    {
        if (in_array($association, $queryBuilder->getAllAliases(), true)) {
            return;
        }

        $queryBuilder->leftJoin($this->getRootAlias($queryBuilder) . '.' . $association, $association);
    }

    private function getRootAlias(QueryBuilder $queryBuilder): string
    {
        return $queryBuilder->getRootAliases()[0] ?? self::ROOT_ALIAS;
    }

}
