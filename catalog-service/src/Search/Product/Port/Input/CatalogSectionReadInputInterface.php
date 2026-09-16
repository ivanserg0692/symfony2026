<?php

namespace App\Search\Product\Port\Input;

use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;

interface CatalogSectionReadInputInterface
{
    /** @return list<CatalogSectionResponse> */
    public function list(): array;

    public function item(int $id): ?CatalogSectionResponse;
}
