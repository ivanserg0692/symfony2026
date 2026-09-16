<?php

namespace App\Search\Product\Application\Dto\Read;

use App\Entity\CatalogSections;
use Symfony\Component\Serializer\Attribute\Groups;
use OpenApi\Attributes as OA;

final readonly class CatalogSectionResponse
{
    public static function fromEntity(CatalogSections $section): self
    {
        return new self(
            $section->getId(), $section->getName(), $section->getSlug(),
            $section->isActive(), $section->getDescription(), $section->getPictureId(),
            $section->getLevel(), $section->getSort(), $section->getParentId(),
        );
    }

    /** @param array<string, mixed> $section */
    public static function fromDocument(array $section): self
    {
        return new self(
            $section["id"], $section["name"], $section["slug"], $section["active"],
            $section["description"], $section["picture_id"], $section["level"],
            $section["sort"], $section["parent_id"],
        );
    }

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
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?string $pictureId,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?int $level,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?int $sort,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:item", "catalog_section:list", "catalog_section:item"])]
        public ?int $parentId,
    ) {
    }
}
