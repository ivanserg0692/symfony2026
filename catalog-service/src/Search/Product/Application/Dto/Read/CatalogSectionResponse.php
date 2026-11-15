<?php

namespace App\Search\Product\Application\Dto\Read;

use App\Media\CatalogPictureResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Component\Serializer\Attribute\Groups;
use OpenApi\Attributes as OA;

final readonly class CatalogSectionResponse
{
    public function __construct(
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?int $id,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?string $name,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?string $slug,
        #[OA\Property(type: "boolean", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?bool $active,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?string $description,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?int $level,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?int $sort,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?int $parentId,
        #[OA\Property(ref: new Model(type: CatalogPictureResponse::class), nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?CatalogPictureResponse $picture = null,
    ) {
    }
}
