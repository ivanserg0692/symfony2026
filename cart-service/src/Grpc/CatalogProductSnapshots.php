<?php

namespace App\Grpc;

final readonly class CatalogProductSnapshots
{
    /**
     * @param list<CatalogProductSnapshot> $snapshots
     */
    public function __construct(
        private array $snapshots,
    ) {
    }

    /**
     * @param int[] $requestedSnapshotIds
     *
     * @return array<int, CatalogProductSnapshot>
     */
    public function indexByRequestedIds(array $requestedSnapshotIds): array
    {
        $snapshotsById = [];

        foreach ($this->snapshots as $snapshot) {
            if (isset($snapshotsById[$snapshot->id])) {
                throw new \LogicException("Catalog product snapshot response contains duplicate snapshots.");
            }

            $snapshotsById[$snapshot->id] = $snapshot;
        }

        $requestedSnapshotIdsById = array_fill_keys($requestedSnapshotIds, true);

        foreach ($snapshotsById as $snapshotId => $_snapshot) {
            if (!isset($requestedSnapshotIdsById[$snapshotId])) {
                throw new \LogicException("Catalog product snapshot response contains an unexpected snapshot.");
            }
        }

        foreach ($requestedSnapshotIds as $snapshotId) {
            if (!isset($snapshotsById[$snapshotId])) {
                throw new \LogicException("Catalog product snapshot response is missing a requested snapshot.");
            }
        }

        return $snapshotsById;
    }
}
