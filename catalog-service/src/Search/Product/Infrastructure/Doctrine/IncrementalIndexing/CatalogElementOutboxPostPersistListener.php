<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing;

use App\Entity\CatalogElements;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Handles the identity-ID timing specific to newly inserted catalog elements.
 *
 * The generated CatalogElement ID is unavailable during onFlush and becomes
 * available only after its INSERT. Keep the ID until the current flush has fully
 * returned; the EntityManager decorator will persist its outbox event through ORM.
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: CatalogElements::class)]
final readonly class CatalogElementOutboxPostPersistListener
{
    public function __construct(
        private CatalogElementOutboxCollector $collector,
    ) {
    }

    public function postPersist(CatalogElements $catalogElement): void
    {
        $catalogElementId = $catalogElement->getId();
        if ($catalogElementId === null) {
            return;
        }

        $this->collector->collect($catalogElementId);
    }
}
