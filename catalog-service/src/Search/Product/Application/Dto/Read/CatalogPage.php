<?php

namespace App\Search\Product\Application\Dto\Read;

final readonly class CatalogPage
{
    /** @param list<CatalogElementResponse> $items */
    public function __construct(
        public array $items,
        public ?int $total,
        public bool $hasNextPage,
    ) {
    }
}
