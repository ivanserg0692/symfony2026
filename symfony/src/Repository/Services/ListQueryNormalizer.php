<?php

namespace App\Repository\Services;

final readonly class ListQueryNormalizer
{
    /**
     * @param non-empty-list<string> $allowedSorts
     */
    public function normalizeSort(string $sort, array $allowedSorts, string $defaultSort): string
    {
        return in_array($sort, $allowedSorts, true) ? $sort : $defaultSort;
    }

    public function normalizeDirection(string $direction): string
    {
        return strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    }

    public function normalizePage(int $page): int
    {
        return max(1, $page);
    }

    public function normalizeLimit(int $limit): int
    {
        return min(100, max(1, $limit));
    }
}
