<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing;

use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Starts an outer transaction before Doctrine computes and executes a flush.
 *
 * Doctrine's onFlush happens before its implicit transaction begins. The outer
 * transaction therefore ensures that DBAL writes performed by Messenger's Doctrine
 * transport in onFlush/postPersist commit or roll back with the business changes.
 */
final class ProductSearchOutboxEntityManagerDecorator extends EntityManagerDecorator
{
    public function __construct(
        EntityManagerInterface $wrapped,
    ) {
        parent::__construct($wrapped);
    }

    public function flush(): void
    {
        $connection = $this->getConnection();

        // Respect a transaction opened by the caller: this decorator must not decide
        // when that transaction is committed or rolled back.
        $ownsTransaction = !$connection->isTransactionActive();

        if ($ownsTransaction) {
            // Messenger's Doctrine transport uses this same DBAL connection directly.
            // Without the outer transaction an onFlush message could be committed
            // before the ORM flush fails, breaking the transactional outbox guarantee.
            $connection->beginTransaction();
        }

        try {
            parent::flush();

            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
