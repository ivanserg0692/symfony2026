<?php

namespace App\Search\Product\Application;

final class ProductSearchRebuildInProgressException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct("Full Elasticsearch product catalog reindex is in progress.");
    }
}
