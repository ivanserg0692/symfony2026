<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing;

use App\Entity\CatalogElements;
use App\Search\Product\Application\Message\ProductSearchOutboxEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles the identity-ID timing specific to newly inserted catalog elements.
 *
 * The generated CatalogElement ID is unavailable during onFlush and becomes
 * available only after its INSERT. Doctrine transport writes the message through
 * the same DBAL connection while the EntityManager decorator's transaction is active.
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: CatalogElements::class)]
final readonly class CatalogElementOutboxPostPersistListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function postPersist(CatalogElements $catalogElement): void
    {
        $catalogElementId = $catalogElement->getId();
        if ($catalogElementId === null) {
            return;
        }

        $this->messageBus->dispatch(new ProductSearchOutboxEvent($catalogElementId));
    }
}
