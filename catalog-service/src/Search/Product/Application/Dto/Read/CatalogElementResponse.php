<?php

namespace App\Search\Product\Application\Dto\Read;

use App\Entity\CatalogElements;
use Symfony\Component\Serializer\Attribute\Groups;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

final readonly class CatalogElementResponse
{
    public static function fromEntity(CatalogElements $element, bool $includeSections): self
    {
        return new self(
            $element->getId(), $element->getName(), $element->isActive(),
            $element->getDescription(), $element->getSlug(), $element->getPictureId(),
            $element->getSort(),
            array_values(array_map(CatalogPriceResponse::fromEntity(...), $element->getProductPrices()->toArray())),
            $element->getTotalStock(),
            $includeSections
                ? array_values(array_map(CatalogSectionResponse::fromEntity(...), $element->getSections()->toArray()))
                : [],
        );
    }

    /** @param array<string, mixed> $document */
    public static function fromDocument(array $document): self
    {
        return new self(
            $document["id"], $document["name"], $document["active"],
            $document["description"], $document["slug"], $document["picture_id"],
            $document["sort"],
            array_map(CatalogPriceResponse::fromDocument(...), $document["prices"]),
            $document["total_stock"],
            array_map(CatalogSectionResponse::fromDocument(...), $document["sections"]),
        );
    }

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
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?string $pictureId,
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
    ) {
    }
}
