<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Implement this interface for every entity type whose changes can affect
 * the Elasticsearch product catalog document.
 *
 * A resolver defines which entity fields and relation collections must be
 * tracked and maps their changes to the affected CatalogElement IDs that
 * must be reindexed.
 */
#[AutoconfigureTag('app.product_search.change_impact_resolver')]
interface ProductSearchChangeImpactResolverInterface
{
    public function supportsEntity(object $entity): bool;

    /**
     * @param string[] $changedFields
     */
    public function hasIndexedChanges(array $changedFields): bool;

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return int[]
     */
    public function resolveEntityChange(object $entity, array $changeSet, bool $insertion): array;

    /**
     * @return int[]
     */
    public function resolvePostPersist(object $entity): array;

    public function supportsCollection(PersistentCollection $collection): bool;

    /**
     * @return int[]
     */
    public function resolveCollectionChange(PersistentCollection $collection): array;
}
