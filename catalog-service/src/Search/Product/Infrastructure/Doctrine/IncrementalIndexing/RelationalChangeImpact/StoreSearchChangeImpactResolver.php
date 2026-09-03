<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing\RelationalChangeImpact;

use App\Entity\Stores;

final readonly class StoreSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
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
        /** @var Stores $entity */
        $catalogElements = [];

        foreach ($entity->getElementStocks() as $elementStock) {
            $catalogElements[] = $elementStock->getElement();
        }

        return $this->entityIds($catalogElements);
    }
}
