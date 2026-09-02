<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\StoresElementsStocks;

final readonly class StockSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    protected function entityClass(): string
    {
        return StoresElementsStocks::class;
    }

    protected function indexedFields(): array
    {
        return ['store', 'element', 'stock'];
    }

    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        /** @var StoresElementsStocks $entity */

        return $this->entityIds([
            $entity->getElement(),
            ...$this->changedAssociationValues($changeSet, 'element'),
        ]);
    }
}
