<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\CatalogElements;

final readonly class CatalogElementSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    protected function entityClass(): string
    {
        return CatalogElements::class;
    }

    protected function indexedFields(): array
    {
        return ['product', 'sort'];
    }

    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        $id = $this->entityId($entity);

        return !$insertion && $id !== null ? [$id] : [];
    }

}
