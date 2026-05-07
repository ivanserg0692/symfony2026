<?php

namespace App\Dto\Sorting;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SearchListQueryDto',
    type: 'object',
    description: 'Query parameters for paginated searchable lists with sorting.',
)]
final readonly class SearchListQueryDto extends ListQueryDto
{
    public function __construct(
        #[OA\Property(description: 'Search query.', default: '', example: 'release')]
        public string $query = '',
        int $page = 1,
        int $limit = 10,
        string $sort = self::DEFAULT_SORT,
        string $direction = self::DEFAULT_DIRECTION,
    ) {
        parent::__construct($page, $limit, $sort, $direction);
    }
}
