<?php

namespace App\Dto;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListQueryDto',
    type: 'object',
    description: 'Query parameters for paginated lists with sorting.',
)]
final readonly class ListQueryDto
{
    public function __construct(
        #[OA\Property(description: 'Page number starting from 1.', minimum: 1, default: 1, example: 1)]
        public int $page = 1,
        #[OA\Property(description: 'Number of items per page.', minimum: 1, maximum: 100, default: 10, example: 10)]
        public int $limit = 10,
        #[OA\Property(description: 'Sort field.', default: 'createdAt', enum: ['id', 'slug', 'createdAt'], example: 'createdAt')]
        public string $sort = 'createdAt',
        #[OA\Property(description: 'Sort direction.', default: 'DESC', enum: ['ASC', 'DESC'], example: 'DESC')]
        public string $direction = 'DESC',
    ) {
    }
}
