<?php

namespace App\Dto\Sorting;

use OpenApi\Attributes as OA;

trait ListSortingFieldsTrait
{
    public const DEFAULT_SORT = 'createdAt';
    public const DEFAULT_DIRECTION = 'DESC';
    public const ALLOWED_SORTS = ['id', 'slug', 'createdAt'];
    public const ALLOWED_DIRECTIONS = ['ASC', 'DESC'];

    #[OA\Property(
        description: 'Sort field.',
        default: self::DEFAULT_SORT,
        enum: self::ALLOWED_SORTS,
        example: self::DEFAULT_SORT,
    )]
    public readonly string $sort;

    #[OA\Property(
        description: 'Sort direction.',
        default: self::DEFAULT_DIRECTION,
        enum: self::ALLOWED_DIRECTIONS,
        example: self::DEFAULT_DIRECTION,
    )]
    public readonly string $direction;
}
