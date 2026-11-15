<?php

namespace App\Search\Product\Application\Dto\Read;

use App\Media\CatalogPictureResponse;
use Symfony\Component\Serializer\Attribute\Groups;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

final readonly class CatalogElementResponse
{
    /**
     * @param list<CatalogPriceResponse> $productPrices
     * @param list<CatalogSectionResponse> $sections
     */
    public function __construct(
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?int $id,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?string $name,
        #[OA\Property(type: "boolean", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?bool $active,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?string $description,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?string $slug,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?int $sort,
        #[OA\Property(type: "array", items: new OA\Items(ref: new Model(type: CatalogPriceResponse::class)))]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public array $productPrices,
        #[OA\Property(type: "integer")]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public int $totalStock,
        #[OA\Property(type: "array", items: new OA\Items(ref: new Model(type: CatalogSectionResponse::class)))]
        #[Groups(["catalog_element:item"])]
        public array $sections,
        #[OA\Property(ref: new Model(type: CatalogPictureResponse::class), nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?CatalogPictureResponse $picture = null,
    ) {
    }
}
