<?php

namespace App\Search\Product\Application\Dto\Document;

final readonly class ProductSearchSection
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $slug,
        public ?bool $active,
        public ?int $parentId,
        public ?int $level,
        public ?int $sort,
    ) {
    }
}
