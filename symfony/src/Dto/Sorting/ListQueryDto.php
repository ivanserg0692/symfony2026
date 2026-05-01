<?php

namespace App\Dto\Sorting;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListQueryDto',
    type: 'object',
    description: 'Query parameters for paginated lists with sorting.',
)]
final readonly class ListQueryDto
{
    use ListSortingFieldsTrait;

    public function __construct(
        #[OA\Property(description: 'Page number starting from 1.', minimum: 1, default: 1, example: 1)]
        public int $page = 1,
        #[OA\Property(description: 'Number of items per page.', minimum: 1, maximum: 100, default: 10, example: 10)]
        public int $limit = 10,
        string $sort = self::DEFAULT_SORT,
        string $direction = self::DEFAULT_DIRECTION,
    ) {
        $this->sort = $sort;
        $this->direction = $direction;
    }
}
