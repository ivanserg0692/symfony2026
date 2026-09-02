<?php

namespace App\Search\Product\Application\Dto\Document;

final readonly class ProductSearchStock
{
    public function __construct(
        public int $storeId,
        public ?string $storeName,
        public ?string $storeSlug,
        public ?bool $storeActive,
        public int $quantity,
    ) {
    }
}
