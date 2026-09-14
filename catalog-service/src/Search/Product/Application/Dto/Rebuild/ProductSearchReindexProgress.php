<?php

namespace App\Search\Product\Application\Dto\Rebuild;

final readonly class ProductSearchReindexProgress
{
    public function __construct(
        public int $processed,
        public int $indexed,
        public int $failed,
        public float $elapsedSeconds,
    ) {
    }

    public function getRate(): float
    {
        return $this->elapsedSeconds > 0.0 ? $this->processed / $this->elapsedSeconds : 0.0;
    }
}
