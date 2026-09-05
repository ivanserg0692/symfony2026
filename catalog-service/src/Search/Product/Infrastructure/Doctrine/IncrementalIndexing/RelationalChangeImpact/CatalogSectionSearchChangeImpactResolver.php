<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing\RelationalChangeImpact;

use App\Entity\CatalogSections;
use App\Repository\CatalogElementsRepository;
use App\Repository\CatalogSectionsRepository;

final readonly class CatalogSectionSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    private const array HIERARCHY_FIELDS = ['level', 'parent', 'leftMargin', 'rightMargin'];

    public function __construct(
        private CatalogSectionsRepository $catalogSectionsRepository,
        private CatalogElementsRepository $catalogElementsRepository,
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
        /** @var CatalogSections $entity */
        $hierarchyChanged = array_intersect(self::HIERARCHY_FIELDS, array_keys($changeSet)) !== [];
        $products = $this->catalogSectionsRepository->collectProducts($entity, $hierarchyChanged);

        return $this->entityIds($this->catalogElementsRepository->findByProducts($products));
    }
}
