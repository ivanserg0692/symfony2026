<?php

namespace App\Search\Product\Application\Dto\Read;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class CatalogListResponse
{
    /** @param list<CatalogElementResponse> $items */
    public function __construct(
        #[OA\Property(type: "array", items: new OA\Items(ref: new Model(type: CatalogElementResponse::class, groups: ["catalog_element:list"])))]
        #[Groups(["catalog_element:list"])]
        public array $items,
        #[OA\Property(
            oneOf: [
                new OA\Schema(
                    required: ["page", "limit", "total"],
                    properties: [
                        new OA\Property(property: "page", type: "integer"),
                        new OA\Property(property: "limit", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                    ],
                    type: "object",
                ),
                new OA\Schema(
                    required: ["page", "limit", "hasNextPage"],
                    properties: [
                        new OA\Property(property: "page", type: "integer"),
                        new OA\Property(property: "limit", type: "integer"),
                        new OA\Property(property: "hasNextPage", type: "boolean"),
                    ],
                    type: "object",
                ),
            ],
        )]
        #[Groups(["catalog_element:list"])]
        public array $pagination,
    ) {
    }
}
