<?php

namespace App\Search\Product\Application\Dto\Indexing;

final readonly class BulkIndexResult
{
    /**
     * @param BulkIndexFailure[] $failures
     */
    public function __construct(
        public int $successful,
        public array $failures,
    ) {
    }

    public function getFailedCount(): int
    {
        return count($this->failures);
    }
}
