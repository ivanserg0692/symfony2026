<?php

namespace App\Search\Product\Application\Dto\Indexing;

final readonly class BulkIndexFailure
{
    public function __construct(
        public int    $productId,
        public string $error,
    )
    {
    }
}
