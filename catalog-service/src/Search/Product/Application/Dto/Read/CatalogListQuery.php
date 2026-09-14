<?php

namespace App\Search\Product\Application\Dto\Read;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CatalogListQuery',
    type: 'object',
    description: 'Query parameters for the catalog element list.',
)]
final readonly class CatalogListQuery
{
    public function __construct(
        #[OA\Property(description: 'Filter by section identifier.', type: 'integer', minimum: 1)]
        public ?int $sectionId = null,
        #[OA\Property(description: 'Filter by active state.', type: 'boolean')]
        public ?string $active = null,
        #[OA\Property(description: 'Page number starting from 1.', type: 'integer', minimum: 1, default: 1)]
        public int $page = 1,
        #[OA\Property(description: 'Number of items per page.', type: 'integer', minimum: 1, maximum: 100, default: 20)]
        public int $limit = 20,
    ) {
    }

    public function toCriteria(bool $lookAhead): CatalogListCriteria
    {
        return new CatalogListCriteria(
            $this->sectionId === null ? null : max(1, $this->sectionId),
            $this->normalizeActive(),
            max(1, $this->page),
            min(100, max(1, $this->limit)),
            $lookAhead,
        );
    }

    private function normalizeActive(): ?bool
    {
        if ($this->active === null) {
            return null;
        }

        $value = filter_var($this->active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($value) ? $value : null;
    }
}
