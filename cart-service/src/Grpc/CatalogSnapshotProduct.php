<?php

namespace App\Grpc;

final readonly class CatalogSnapshotProduct
{
    public function __construct(
        public int $id,
        public string $name,
        public string $createdAt,
        public bool $active,
        public int $createdBy,
        public ?string $description,
        public string $slug,
        public ?string $pictureId,
    ) {
    }
}
