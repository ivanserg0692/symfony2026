<?php

namespace App\Search\Product\Application\Dto\Read;

use App\Entity\ProductPrice;
use Symfony\Component\Serializer\Attribute\Groups;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

final readonly class CatalogPriceResponse
{
    public static function fromEntity(ProductPrice $price): self
    {
        return new self(
            $price->getId(), CatalogPriceTypeResponse::fromEntity($price->getPriceType()),
            $price->getPrice(), $price->getCurrency(), $price->isActive(),
            $price->getValidFrom(), $price->getValidTo(),
        );
    }

    /** @param array<string, mixed> $price */
    public static function fromDocument(array $price): self
    {
        return new self(
            $price["id"],
            CatalogPriceTypeResponse::fromDocument($price),
            $price["amount"], $price["currency"], $price["active"],
            self::date($price["valid_from"]), self::date($price["valid_to"]),
        );
    }

    public function __construct(
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?int $id,
        #[OA\Property(ref: new Model(type: CatalogPriceTypeResponse::class), nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?CatalogPriceTypeResponse $priceType,
        #[OA\Property(type: "integer", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?int $price,
        #[OA\Property(type: "string", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?string $currency,
        #[OA\Property(type: "boolean", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?bool $active,
        #[OA\Property(type: "string", format: "date-time", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?\DateTimeImmutable $validFrom,
        #[OA\Property(type: "string", format: "date-time", nullable: true)]
        #[Groups(["catalog_element:list", "catalog_element:item"])]
        public ?\DateTimeImmutable $validTo,
    ) {
    }

    private static function date(?string $value): ?\DateTimeImmutable
    {
        return $value === null ? null : new \DateTimeImmutable($value);
    }
}
