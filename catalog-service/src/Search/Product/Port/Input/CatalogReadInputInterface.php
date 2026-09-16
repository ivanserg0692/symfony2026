<?php

namespace App\Search\Product\Port\Input;

use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Application\Dto\Read\CatalogListResponse;

interface CatalogReadInputInterface
{
    public function list(CatalogListCriteria $criteria): CatalogListResponse;

    public function item(int $id): ?CatalogElementResponse;
}
