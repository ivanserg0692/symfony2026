<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\CatalogSections;
use App\Entity\Product;
use App\Repository\CatalogElementsRepository;

final readonly class CatalogSectionSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    private const array HIERARCHY_FIELDS = ['level', 'parent', 'leftMargin', 'rightMargin'];

    public function __construct(
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
        $products = $this->collectProducts($entity, $hierarchyChanged);

        return $this->entityIds($this->catalogElementsRepository->findByProducts($products));
    }

    /**
     * Hierarchy changes also alter indexed level/parent data for products assigned
     * to descendant sections, so their products are collected recursively.
     *
     * @return Product[]
     */
    private function collectProducts(CatalogSections $section, bool $includeDescendants): array
    {
        $products = [];
        $visitedSections = [];
        $sections = [$section];

        while ($sections !== []) {
            $currentSection = array_pop($sections);
            $objectId = spl_object_id($currentSection);

            if (isset($visitedSections[$objectId])) {
                continue;
            }

            $visitedSections[$objectId] = true;

            foreach ($currentSection->getProducts() as $product) {
                $productId = $product->getId();
                if ($productId !== null) {
                    $products[$productId] = $product;
                }
            }

            if ($includeDescendants) {
                foreach ($currentSection->getCatalogSections() as $childSection) {
                    $sections[] = $childSection;
                }
            }
        }

        return array_values($products);
    }
}
