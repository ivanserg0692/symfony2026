<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\Product;
use App\Repository\CatalogElementsRepository;
use Doctrine\ORM\PersistentCollection;

final readonly class ProductSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    public function __construct(
        private CatalogElementsRepository $catalogElementsRepository,
    ) {
    }

    protected function entityClass(): string
    {
        return Product::class;
    }

    protected function indexedFields(): array
    {
        return ['name', 'createdAt', 'active', 'description', 'slug', 'pictureId'];
    }

    protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        /** @var Product $entity */

        return $this->entityIds($this->catalogElementsRepository->findByProducts([$entity]));
    }

    public function supportsCollection(PersistentCollection $collection): bool
    {
        return $collection->getOwner() instanceof Product
            && $collection->getMapping()->fieldName === 'sections';
    }

    public function resolveCollectionChange(PersistentCollection $collection): array
    {
        if (!$this->supportsCollection($collection)) {
            throw new \LogicException(sprintf('%s does not support this collection.', static::class));
        }

        return $this->entityIds($this->catalogElementsRepository->findByProducts([$collection->getOwner()]));
    }
}
