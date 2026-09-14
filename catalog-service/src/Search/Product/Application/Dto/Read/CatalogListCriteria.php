<?php

namespace App\Search\Product\Application\Dto\Read;

final readonly class CatalogListCriteria
{
    public function __construct(
        public ?int $sectionId,
        public ?bool $active,
        public int $page,
        public int $limit,
        public bool $lookAhead,
    ) {
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function getFetchSize(): int
    {
        return $this->limit + ($this->lookAhead ? 1 : 0);
    }
}
