<?php

namespace App\Search\Product\Port\Input;

/**
 * Application input port for bringing one catalog document to its current PostgreSQL state.
 */
interface ProductSearchIncrementalIndexInterface
{
    public function reindex(int $catalogElementId): void;
}
