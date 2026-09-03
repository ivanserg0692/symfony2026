<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing;

use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists outbox events for new identity-generated CatalogElements only after
 * their original flush has fully returned, while keeping both flushes atomic.
 */
final class ProductSearchOutboxEntityManagerDecorator extends EntityManagerDecorator
{
    public function __construct(
        EntityManagerInterface $wrapped,
        private readonly CatalogElementOutboxCollector $collector,
        private readonly ProductSearchOutboxWriter $outboxWriter,
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
            // Two ORM flushes are required for a new CatalogElement: the first one
            // generates its identity, and the second one persists its outbox event.
            // Without this surrounding transaction Doctrine could commit the business
            // data before the outbox flush fails, breaking transactional outbox delivery.
            $connection->beginTransaction();
        }

        try {
            $this->collector->begin();
            parent::flush();

            $catalogElementIds = $this->collector->release();
            foreach ($catalogElementIds as $catalogElementId) {
                $this->outboxWriter->scheduleForNextFlush($this->wrapped, $catalogElementId);
            }

            if ($catalogElementIds !== []) {
                parent::flush();
            }

            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            $this->collector->discard();

            if ($ownsTransaction && $connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
