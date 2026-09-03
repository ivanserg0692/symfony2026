<?php

namespace App\Search\Product\Infrastructure\Doctrine;

use App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact\ProductSearchChangeImpactResolverInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsDoctrineListener(event: Events::onFlush, priority: -100)]
final readonly class ProductSearchOutboxDoctrineListener
{
    /** @var ProductSearchChangeImpactResolverInterface[] */
    private array $changeImpactResolvers;

    /**
     * @param iterable<ProductSearchChangeImpactResolverInterface> $changeImpactResolvers
     */
    public function __construct(
        #[AutowireIterator('app.product_search.change_impact_resolver')]
        iterable                          $changeImpactResolvers,
        private ProductSearchOutboxWriter $outboxWriter,
    )
    {
        $this->changeImpactResolvers = [...$changeImpactResolvers];
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $catalogElementIds = [];

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->addIds(
                $catalogElementIds,
                $this->resolverForEntity($entity)?->resolveEntityChange($entity, [], true) ?? [],
            );
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            $resolver = $this->resolverForEntity($entity);
            if ($resolver?->hasIndexedChanges(array_keys($changeSet)) === true) {
                $this->addIds(
                    $catalogElementIds,
                    $resolver->resolveEntityChange($entity, $changeSet, false),
                );
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $this->addIds(
                $catalogElementIds,
                $this->resolverForEntity($entity)?->resolveEntityChange($entity, [], false) ?? [],
            );
        }

        // Doctrine tracks collection changes separately from entity updates. This captures
        // Product::sections join-table changes, which do not schedule a Product entity update.
        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $this->collectCollectionOwnerId($collection, $catalogElementIds);
        }

        foreach (array_keys($catalogElementIds) as $catalogElementId) {
            $this->outboxWriter->scheduleInCurrentFlush($entityManager, $catalogElementId);
        }
    }

    /**
     * @param array<int, true> $catalogElementIds
     */
    private function collectCollectionOwnerId(object $collection, array &$catalogElementIds): void
    {
        if (!$collection instanceof PersistentCollection) {
            return;
        }

        foreach ($this->changeImpactResolvers as $resolver) {
            if ($resolver->supportsCollection($collection)) {
                $this->addIds($catalogElementIds, $resolver->resolveCollectionChange($collection));

                return;
            }
        }
    }

    private function resolverForEntity(object $entity): ?ProductSearchChangeImpactResolverInterface
    {
        foreach ($this->changeImpactResolvers as $resolver) {
            if ($resolver->supportsEntity($entity)) {
                return $resolver;
            }
        }
        return null;
    }

    /**
     * @param array<int, true> $catalogElementIds
     * @param int[] $ids
     */
    private function addIds(array &$catalogElementIds, array $ids): void
    {
        foreach ($ids as $id) {
            $catalogElementIds[$id] = true;
        }
    }
}
