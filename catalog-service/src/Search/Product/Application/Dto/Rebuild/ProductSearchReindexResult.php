<?php

namespace App\Search\Product\Application\Dto\Rebuild;

final readonly class ProductSearchReindexResult
{
    public function __construct(
        public string $indexName,
        public int $processed,
        public int $indexed,
        public int $failed,
        public float $elapsedSeconds,
        public bool $aliasSwitched,
    ) {
    }
}
