<?php

namespace App\Search\Product\Port\Output;

use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Application\Dto\Read\CatalogPage;

interface CatalogReadInterface
{
    public function findPage(CatalogListCriteria $criteria): CatalogPage;

    public function findById(int $id): ?CatalogElementResponse;
}
