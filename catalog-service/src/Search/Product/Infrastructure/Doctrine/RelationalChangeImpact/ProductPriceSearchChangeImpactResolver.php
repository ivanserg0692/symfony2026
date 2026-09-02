<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\ProductPrice;

final readonly class ProductPriceSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    protected function entityClass(): string
    {
        return ProductPrice::class;
    }

    protected function indexedFields(): array
    {
        return ['product', 'priceType', 'price', 'currency', 'active', 'validFrom', 'validTo'];
    }

    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        /** @var ProductPrice $entity */

        // Reindex both the current and previous catalog elements when the price
        // is reassigned, so the price is removed from the old document as well.
        return $this->entityIds([
            $entity->getProduct(),
            ...$this->changedAssociationValues($changeSet, 'product'),
        ]);
    }
}
