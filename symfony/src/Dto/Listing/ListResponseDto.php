<?php

namespace App\Dto\Listing;

use App\Dto\Sorting\ListSortingDto;
use JsonSerializable;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Pagerfanta\PagerfantaInterface;

#[OA\Schema(
    schema: 'ListResponseDto',
    type: 'object',
    description: 'Paginated list response.',
    properties: [
        new OA\Property(
            property: 'pagination',
            ref: new Model(type: ListPaginationDto::class),
        ),
        new OA\Property(
            property: 'sorting',
            ref: new Model(type: ListSortingDto::class),
        ),
    ],
)]
final readonly class ListResponseDto implements JsonSerializable
{
    /**
     * @param array<mixed> $items
     */
    private function __construct(
        private array $items,
        private ListPaginationDto $pagination,
        private ListSortingDto $sorting,
    ) {
    }

    public static function fromPager(
        PagerfantaInterface $pager,
        string $sort,
        string $direction,
    ): self {
        return new self(
            iterator_to_array($pager->getCurrentPageResults()),
            new ListPaginationDto(
                $pager->getCurrentPage(),
                $pager->getMaxPerPage(),
                $pager->getNbResults(),
                $pager->getNbPages(),
            ),
            new ListSortingDto($sort, $direction),
        );
    }

    /**
     * @return array{
     *     items: array<mixed>,
     *     pagination: array{page: int, limit: int, total: int, pages: int},
     *     sorting: array{sort: string, direction: string}
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'pagination' => $this->pagination->jsonSerialize(),
            'sorting' => $this->sorting->jsonSerialize(),
        ];
    }
}
