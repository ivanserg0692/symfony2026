<?php

namespace App\Search\Product\Port\Output;

use App\Entity\CatalogElements;

interface ProductSearchCatalogSourceInterface
{
    public function countProducts(): int;

    /**
     * @return int[]
     */
    public function findIdsAfter(int $lastId, int $limit): array;

    /**
     * @param int[] $ids
     *
     * @return CatalogElements[]
     */
    public function loadByIds(array $ids): array;

    public function releaseLoadedBatch(): void;
}
