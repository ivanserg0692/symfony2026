<?php

namespace App\Search\Product\Infrastructure\Doctrine;

use App\Entity\CatalogElements;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Handles the identity-ID timing specific to newly inserted catalog elements.
 *
 * The generated CatalogElement ID is unavailable during onFlush and becomes
 * available only after its INSERT. The outbox row is then inserted while the
 * current Doctrine transaction is still active.
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: CatalogElements::class)]
final readonly class CatalogElementOutboxPostPersistListener
{
    public function __construct(
        private ProductSearchOutboxWriter $outboxWriter,
    ) {
    }

    public function postPersist(CatalogElements $catalogElement, PostPersistEventArgs $event): void
    {
        $catalogElementId = $catalogElement->getId();
        if ($catalogElementId === null) {
            return;
        }

        $this->outboxWriter->insertAfterIdentityGenerated($catalogElementId);
    }
}
