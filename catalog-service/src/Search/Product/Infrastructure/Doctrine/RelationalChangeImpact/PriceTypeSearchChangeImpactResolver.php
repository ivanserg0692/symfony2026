<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\PriceType;

final readonly class PriceTypeSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    protected function entityClass(): string
    {
        return PriceType::class;
    }

    protected function indexedFields(): array
    {
        return ['code', 'name', 'active'];
    }

    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        /** @var PriceType $entity */
        $catalogElements = [];

        foreach ($entity->getProductPrices() as $productPrice) {
            $catalogElements[] = $productPrice->getProduct();
        }

        return $this->entityIds($catalogElements);
    }
}
