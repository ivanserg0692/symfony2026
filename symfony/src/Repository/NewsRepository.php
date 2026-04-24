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
    private const ALLOWED_SORTS = ['id', 'slug', 'createdAt'];
    private const AUTHOR_ALIAS = 'author';
    private const STATUS_ALIAS = 'status';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    public function createListQueryBuilder(ListQueryDto $query, ?User $user): QueryBuilder
    {
        $queryBuilder = $this->createVisibleQueryBuilder($user);

        return $queryBuilder->orderBy(
            'news.' . $this->normalizeSort($query->sort),
            $this->normalizeDirection($query->direction)
        );
    }

    public function createVisibleQueryBuilder(?User $user): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('news');

        $this->addListRelations($queryBuilder, 'news');
        $this->applyVisibility($queryBuilder, $user);

        return $queryBuilder;
    }

    public function addListRelations(QueryBuilder $queryBuilder, string $rootAlias): QueryBuilder
    {
        $aliases = $queryBuilder->getAllAliases();

        if (!in_array(self::AUTHOR_ALIAS, $aliases, true)) {
            $queryBuilder
                ->leftJoin($rootAlias . '.createdBy', self::AUTHOR_ALIAS)
                ->addSelect(self::AUTHOR_ALIAS);
        }

        if (!in_array(self::STATUS_ALIAS, $aliases, true)) {
            $queryBuilder
                ->leftJoin($rootAlias . '.status', self::STATUS_ALIAS)
                ->addSelect(self::STATUS_ALIAS);
        }

        return $queryBuilder;
    }

    public function applyVisibility(QueryBuilder $queryBuilder, ?User $user): QueryBuilder
    {
        if (!$user instanceof User) {
            return $queryBuilder
                ->andWhere(self::STATUS_ALIAS . '.code = :publicStatus')
                ->setParameter('publicStatus', NewsStatusCode::PUBLIC);
        }

        if ($user->isAdmin()) {
            return $queryBuilder;
        }

        return $queryBuilder
            ->andWhere(sprintf('(%s.code IN (:visibleStatuses) OR %s = :user)', self::STATUS_ALIAS, self::AUTHOR_ALIAS))
            ->setParameter('visibleStatuses', [NewsStatusCode::PUBLIC, NewsStatusCode::INTERNAL])
            ->setParameter('user', $user);
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
        return in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'createdAt';
    }

    public function normalizeDirection(string $direction): string
    {
        return strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    }
}
