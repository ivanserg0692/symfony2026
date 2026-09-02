<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\PriceType;
use App\Search\Product\Infrastructure\Doctrine\ProductSearchAffectedElementResolver;

final readonly class PriceTypeSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    public function __construct(
        private ProductSearchAffectedElementResolver $affectedElementResolver,
    ) {
    }

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
        return $this->affectedElementResolver->byPriceTypeIds($this->entityIds([$entity]));
    }
}
