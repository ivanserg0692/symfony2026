<?php

namespace App\Search\Product\Port\Output;

use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;

interface CatalogSectionReadInterface
{
    /** @return list<CatalogSectionResponse> */
    public function findActive(): array;

    public function findById(int $id): ?CatalogSectionResponse;
}
