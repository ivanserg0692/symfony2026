<?php

namespace App\Dto\Sorting;

use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListSortingDto',
    type: 'object',
    description: 'Applied sorting metadata for list responses.',
)]
final readonly class ListSortingDto implements JsonSerializable
{
    use ListSortingFieldsTrait;

    public function __construct(
        string $sort = self::DEFAULT_SORT,
        string $direction = self::DEFAULT_DIRECTION,
    ) {
        $this->sort = $sort;
        $this->direction = $direction;
    }

    /**
     * @return array{sort: string, direction: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }
}
