<?php

namespace App\Search\Product\Port\Output;

interface ProductSearchReindexLockInterface
{
    /**
     * Acquires the exclusive lock used by full reindex.
     */
    public function acquire(): bool;

    public function release(): void;

    /**
     * Acquires a shared lock for one incremental indexing operation.
     *
     * Multiple incremental workers may hold this lock concurrently, while a full
     * reindex holding the exclusive lock prevents new incremental operations.
     */
    public function acquireShared(): bool;

    public function releaseShared(): void;
}
