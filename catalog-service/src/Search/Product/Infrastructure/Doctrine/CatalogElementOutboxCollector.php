<?php

namespace App\Search\Product\Infrastructure\Doctrine;

/**
 * Collects identity IDs assigned during one Doctrine flush.
 *
 * Collection is explicitly scoped by ProductSearchOutboxEntityManagerDecorator
 * so failed flushes cannot leak IDs into later units of work in long-running workers.
 */
final class CatalogElementOutboxCollector
{
    /** @var array<int, true> */
    private array $catalogElementIds = [];

    private bool $collecting = false;

    public function begin(): void
    {
        if ($this->collecting) {
            throw new \LogicException("Catalog element outbox collection is already active.");
        }

        $this->catalogElementIds = [];
        $this->collecting = true;
    }

    public function collect(int $catalogElementId): void
    {
        if ($catalogElementId < 1) {
            return;
        }

        if (!$this->collecting) {
            throw new \LogicException("Catalog element ID was generated outside the coordinated Doctrine flush.");
        }

        $this->catalogElementIds[$catalogElementId] = true;
    }

    /**
     * @return int[]
     */
    public function release(): array
    {
        $catalogElementIds = array_keys($this->catalogElementIds);
        $this->discard();

        return $catalogElementIds;
    }

    public function discard(): void
    {
        $this->catalogElementIds = [];
        $this->collecting = false;
    }
}
