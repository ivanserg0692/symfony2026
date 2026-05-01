<?php

namespace App\Dto\Listing;

use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListPaginationDto',
    type: 'object',
    description: 'Pagination metadata for list responses.',
)]
final readonly class ListPaginationDto implements JsonSerializable
{
    public function __construct(
        #[OA\Property(description: 'Current page number.', example: 1)]
        public int $page,
        #[OA\Property(description: 'Maximum number of items per page.', example: 10)]
        public int $limit,
        #[OA\Property(description: 'Total number of items.', example: 42)]
        public int $total,
        #[OA\Property(description: 'Total number of pages.', example: 5)]
        public int $pages,
    ) {
    }

    /**
     * @return array{page: int, limit: int, total: int, pages: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'total' => $this->total,
            'pages' => $this->pages,
        ];
    }
}
