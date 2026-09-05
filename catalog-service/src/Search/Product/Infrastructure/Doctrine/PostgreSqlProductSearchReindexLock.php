<?php

namespace App\Search\Product\Infrastructure\Doctrine;

use App\Search\Product\Port\Output\ProductSearchReindexLockInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProductSearchReindexLockInterface::class)]
final class PostgreSqlProductSearchReindexLock implements ProductSearchReindexLockInterface
{
    private bool $acquired = false;
    private bool $sharedAcquired = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly int $productSearchReindexLockId,
    ) {
    }

    public function acquire(): bool
    {
        if ($this->acquired) {
            return true;
        }

        $this->acquired = (bool) $this->connection->fetchOne(
            "SELECT pg_try_advisory_lock(:lockId)",
            ["lockId" => $this->productSearchReindexLockId],
        );

        return $this->acquired;
    }

    public function release(): void
    {
        if (!$this->acquired) {
            return;
        }

        $this->connection->executeQuery(
            "SELECT pg_advisory_unlock(:lockId)",
            ["lockId" => $this->productSearchReindexLockId],
        );
        $this->acquired = false;
    }

    public function acquireShared(): bool
    {
        if ($this->sharedAcquired) {
            return true;
        }

        $this->sharedAcquired = (bool) $this->connection->fetchOne(
            "SELECT pg_try_advisory_lock_shared(:lockId)",
            ["lockId" => $this->productSearchReindexLockId],
        );

        return $this->sharedAcquired;
    }

    public function releaseShared(): void
    {
        if (!$this->sharedAcquired) {
            return;
        }

        $this->connection->executeQuery(
            "SELECT pg_advisory_unlock_shared(:lockId)",
            ["lockId" => $this->productSearchReindexLockId],
        );
        $this->sharedAcquired = false;
    }
}
