<?php

namespace App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing\RelationalChangeImpact;

use App\Entity\CatalogSections;
use App\Repository\CatalogElementsRepository;
use App\Repository\CatalogSectionsRepository;

/**
 * Identifies catalog element documents affected by changes to a catalog section.
 *
 * Regular field changes affect products assigned directly to the section;
 * hierarchy changes also affect products assigned to descendant sections.
 * Returns CatalogElements IDs for incremental indexing; does not update Elasticsearch itself.
 */
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

    /**
     * Section changes that require rebuilding product search documents.
     * Includes the picture association because image metadata is embedded in the documents.
     *
     * @return string[]
     */
    protected function indexedFields(): array
    {
        return ['name', 'slug', 'active', ...self::HIERARCHY_FIELDS, 'sort', 'description', 'picture'];
    }

    /**
     * Resolves affected CatalogElements IDs, including descendant products only
     * when level, parent, leftMargin or rightMargin changes.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return int[]
     */
    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        /** @var CatalogSections $entity */
        $hierarchyChanged = array_intersect(self::HIERARCHY_FIELDS, array_keys($changeSet)) !== [];
        $products = $this->catalogSectionsRepository->collectProducts($entity, $hierarchyChanged);

        return $this->entityIds($this->catalogElementsRepository->findByProducts($products));
    }
}
