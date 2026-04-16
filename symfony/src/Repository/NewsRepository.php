<?php

namespace App\Repository;

use App\Dto\ListQueryDto;
use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 */
class NewsRepository extends ServiceEntityRepository
{
    private const ALLOWED_SORTS = ['id', 'slug', 'createdAt'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    public function createListQueryBuilder(ListQueryDto $query): QueryBuilder
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.createdBy', 'u')
            ->addSelect('u')
            ->orderBy('n.' . $this->normalizeSort($query->sort), $this->normalizeDirection($query->direction));
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
