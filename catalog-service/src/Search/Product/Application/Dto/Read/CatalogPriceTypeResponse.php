<?php

namespace App\Search\Product\Application\Dto\Read;

use App\Entity\PriceType;
use Symfony\Component\Serializer\Attribute\Groups;
use OpenApi\Attributes as OA;

final readonly class CatalogPriceTypeResponse
{
    public static function fromEntity(?PriceType $type): ?self
    {
        return $type === null ? null : new self(
            $type->getId(), $type->getCode(), $type->getName(),
            $type->getDescription(), $type->isActive(), $type->getSort(),
        );
    }

    /** @param array<string, mixed> $price */
    public static function fromDocument(array $price): ?self
    {
        if ($price["type_id"] === null) {
            return null;
        }

        return new self(
            $price["type_id"], $price["type_code"], $price["type_name"],
            $price["type_description"], $price["type_active"], $price["type_sort"],
        );
    }

    public function __construct(
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?int $id,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?string $code,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?string $name,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:item"])]
        public ?string $description,
        #[OA\Property(type: "boolean", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?bool $active,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?int $sort,
    ) {
    }
}
