<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing;

use App\Entity\ProductSearchOutboxEvent;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductSearchOutboxWriter
{
    /**
     * Adds an outbox event after Doctrine has already computed the current flush.
     */
    public function scheduleInCurrentFlush(EntityManagerInterface $entityManager, int $catalogElementId): void
    {
        $event = $this->createEvent($catalogElementId);
        if ($event === null) {
            return;
        }

        $entityManager->persist($event);
        $entityManager->getUnitOfWork()->computeChangeSet(
            $entityManager->getClassMetadata(ProductSearchOutboxEvent::class),
            $event,
        );
    }

    /**
     * Adds an outbox event before a separate, subsequent Doctrine flush.
     */
    public function scheduleForNextFlush(EntityManagerInterface $entityManager, int $catalogElementId): void
    {
        $event = $this->createEvent($catalogElementId);
        if ($event === null) {
            return;
        }

        $entityManager->persist($event);
    }

    private function createEvent(int $catalogElementId): ?ProductSearchOutboxEvent
    {
        return $catalogElementId < 1 ? null : new ProductSearchOutboxEvent($catalogElementId);
    }
}
