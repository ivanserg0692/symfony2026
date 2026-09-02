<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use App\Entity\Product;
use App\Search\Product\Infrastructure\Doctrine\ProductSearchAffectedElementResolver;
use Doctrine\ORM\PersistentCollection;

final readonly class ProductSearchChangeImpactResolver extends AbstractProductSearchChangeImpactResolver
{
    public function __construct(
        private ProductSearchAffectedElementResolver $affectedElementResolver,
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
        return $this->affectedElementResolver->byProductIds($this->entityIds([$entity]));
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

        return $this->affectedElementResolver->byProductIds($this->entityIds([$collection->getOwner()]));
    }
}
