<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\CatalogSections;
use App\Search\Product\Infrastructure\Doctrine\ProductSearchAffectedElementResolver;

final readonly class CatalogSectionSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    private const array HIERARCHY_FIELDS = ['level', 'parent', 'leftMargin', 'rightMargin'];

    public function __construct(
        private ProductSearchAffectedElementResolver $affectedElementResolver,
    ) {
    }

    protected function entityClass(): string
    {
        return CatalogSections::class;
    }

    protected function indexedFields(): array
    {
        return ['name', 'slug', 'active', ...self::HIERARCHY_FIELDS, 'sort'];
    }

    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        $hierarchyChanged = array_intersect(self::HIERARCHY_FIELDS, array_keys($changeSet)) !== [];

        return $this->affectedElementResolver->bySectionIds($this->entityIds([$entity]), $hierarchyChanged);
    }
}
