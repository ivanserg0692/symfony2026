<?php

namespace App\Search\Product\Application\Dto\Document;

use App\Search\Product\Port\Output\Document\ProductSearchIndexDocumentInterface;

final readonly class ProductSearchDocument implements ProductSearchIndexDocumentInterface
{
    /**
     * @param ProductSearchSection[] $sections
     * @param ProductSearchPrice[] $prices
     * @param ProductSearchStock[] $stocks
     */
    public function __construct(
        public int $id,
        public int $productId,
        public ?string $name,
        public ?string $slug,
        public ?string $description,
        public ?string $pictureId,
        public ?bool $active,
        public ?int $sort,
        public ?\DateTimeImmutable $createdAt,
        public array $sections,
        public array $prices,
        public array $stocks,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $sections = array_map(
            static fn(ProductSearchSection $section): array => [
                "id" => $section->id,
                "name" => $section->name,
                "slug" => $section->slug,
                "active" => $section->active,
                "parent_id" => $section->parentId,
                "level" => $section->level,
                "sort" => $section->sort,
            ],
            $this->sections,
        );

        $prices = array_map(
            static fn(ProductSearchPrice $price): array => [
                "id" => $price->id,
                "type_id" => $price->typeId,
                "type_code" => $price->typeCode,
                "type_name" => $price->typeName,
                "type_active" => $price->typeActive,
                "amount" => $price->amount,
                "currency" => $price->currency,
                "active" => $price->active,
                "valid_from" => $price->validFrom?->format(\DateTimeInterface::ATOM),
                "valid_to" => $price->validTo?->format(\DateTimeInterface::ATOM),
            ],
            $this->prices,
        );

        $stocks = array_map(
            static fn(ProductSearchStock $stock): array => [
                "store_id" => $stock->storeId,
                "store_name" => $stock->storeName,
                "store_slug" => $stock->storeSlug,
                "store_active" => $stock->storeActive,
                "quantity" => $stock->quantity,
            ],
            $this->stocks,
        );

        $totalStock = array_sum(array_column($stocks, "quantity"));

        return [
            "id" => $this->id,
            "product_id" => $this->productId,
            "name" => $this->name,
            "slug" => $this->slug,
            "description" => $this->description,
            "picture_id" => $this->pictureId,
            "active" => $this->active,
            "sort" => $this->sort,
            "created_at" => $this->createdAt?->format(\DateTimeInterface::ATOM),
            "section_ids" => array_column($sections, "id"),
            "sections" => $sections,
            "prices" => $prices,
            "total_stock" => $totalStock,
            "available" => $totalStock > 0,
            "stocks" => $stocks,
        ];
    }
}
