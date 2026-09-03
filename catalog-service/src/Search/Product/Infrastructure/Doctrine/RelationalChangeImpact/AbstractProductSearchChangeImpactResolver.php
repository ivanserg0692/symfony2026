<?php

namespace App\Search\Product\Infrastructure\Doctrine\RelationalChangeImpact;

use Doctrine\ORM\PersistentCollection;

abstract readonly class AbstractProductSearchChangeImpactResolver implements ProductSearchChangeImpactResolverInterface
{
    final public function supportsEntity(object $entity): bool
    {
        $entityClass = $this->entityClass();

        return $entity instanceof $entityClass;
    }

    final public function hasIndexedChanges(array $changedFields): bool
    {
        return array_intersect($this->indexedFields(), $changedFields) !== [];
    }

    final public function resolveEntityChange(object $entity, array $changeSet, bool $insertion): array
    {
        if (!$this->supportsEntity($entity)) {
            throw new \LogicException(sprintf('%s does not support %s.', static::class, $entity::class));
        }

        return $this->doResolveEntityChange($entity, $changeSet, $insertion);
    }

    public function supportsCollection(PersistentCollection $collection): bool
    {
        return false;
    }

    public function resolveCollectionChange(PersistentCollection $collection): array
    {
        throw new \LogicException(sprintf('%s does not support collection changes.', static::class));
    }

    /**
     * @return class-string
     */
    abstract protected function entityClass(): string;

    /**
     * @return string[]
     */
    abstract protected function indexedFields(): array;

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return int[]
     */
    abstract protected function doResolveEntityChange(object $entity, array $changeSet, bool $insertion): array;

    protected function entityId(object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            return null;
        }

        $id = $entity->getId();

        return is_int($id) ? $id : null;
    }

    /**
     * @param array<null|object> $entities
     *
     * @return int[]
     */
    protected function entityIds(array $entities): array
    {
        $ids = [];

        foreach ($entities as $entity) {
            if ($entity === null) {
                continue;
            }

            $id = $this->entityId($entity);
            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return object[]
     */
    protected function changedAssociationValues(array $changeSet, string $field): array
    {
        return array_values(array_filter(
            $changeSet[$field] ?? [],
            static fn(mixed $value): bool => is_object($value),
        ));
    }
}
