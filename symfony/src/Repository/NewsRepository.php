<?php

namespace App\Repository;

use App\Dto\ListQueryDto;
use App\Entity\News;
use App\Entity\User;
use App\Enum\NewsStatusCode;
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
    private const DEFAULT_SORT = 'createdAt';
    private const ALLOWED_SORTS = ['id', 'slug', 'createdAt'];
    private const CREATED_BY_ALIAS = self::CREATED_BY_ASSOCIATION;
    private const STATUS_ALIAS = 'status';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    public function createListQueryBuilder(ListQueryDto $query, ?User $user): QueryBuilder
    {
        $queryBuilder = $this->createVisibleQueryBuilder($user);

        return $queryBuilder->orderBy(
            self::ROOT_ALIAS . '.' . $this->normalizeSort($query->sort),
            $this->normalizeDirection($query->direction)
        );
    }

    public function createVisibleQueryBuilder(?User $user): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder(self::ROOT_ALIAS);

        $this->applyVisibility($queryBuilder, $user);

        return $queryBuilder;
    }

    public function addListRelations(QueryBuilder $queryBuilder, string $rootAlias): QueryBuilder
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
        $rootAlias = $this->getRootAlias($queryBuilder);
        $this->addListRelations($queryBuilder, $rootAlias);

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

    public function normalizePage(int $page): int
    {
        return max(1, $page);
    }

    public function normalizeLimit(int $limit): int
    {
        return min(100, max(1, $limit));
    }

    public function normalizeSort(string $sort): string
    {
        return in_array($sort, self::ALLOWED_SORTS, true) ? $sort : self::DEFAULT_SORT;
    }

    public function normalizeDirection(string $direction): string
    {
        return strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    }
}
