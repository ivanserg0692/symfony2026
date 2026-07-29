<?php

namespace App\Grpc;

final readonly class CatalogProductSnapshot
{
    public function __construct(
        public int $id,
        public int $originalProductId,
        public CatalogSnapshotProduct $product,
    ) {
    }
}
