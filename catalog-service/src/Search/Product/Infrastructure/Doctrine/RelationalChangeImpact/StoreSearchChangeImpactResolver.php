<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\Stores;
use App\Search\Product\Infrastructure\Doctrine\ProductSearchAffectedElementResolver;

final readonly class StoreSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    public function __construct(
        private ProductSearchAffectedElementResolver $affectedElementResolver,
    ) {
    }

    protected function entityClass(): string
    {
        return Stores::class;
    }

    protected function indexedFields(): array
    {
        return ['name', 'slug', 'active'];
    }

    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        return $this->affectedElementResolver->byStoreIds($this->entityIds([$entity]));
    }
}
