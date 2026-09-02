<?php

namespace App\Search\Product\Application\Dto\Document;

final readonly class ProductSearchPrice
{
    public function __construct(
        public int $id,
        public ?int $typeId,
        public ?string $typeCode,
        public ?string $typeName,
        public ?bool $typeActive,
        public ?int $amount,
        public ?string $currency,
        public ?bool $active,
        public ?\DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validTo,
    ) {
    }
}
