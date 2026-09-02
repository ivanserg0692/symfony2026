<?php

namespace App\Search\Product\Port\Input;

use App\Search\Product\Application\Dto\Rebuild\ProductSearchReindexProgress;
use App\Search\Product\Application\Dto\Rebuild\ProductSearchReindexResult;

interface ProductSearchRebuildInterface
{
    public function countProducts(): int;

    /**
     * @param null|callable(ProductSearchReindexProgress): void $onProgress
     */
    public function rebuild(?callable $onProgress = null): ProductSearchReindexResult;
}
