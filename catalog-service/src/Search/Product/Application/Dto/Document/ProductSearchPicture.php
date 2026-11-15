<?php

namespace App\Search\Product\Application\Dto\Document;

use App\Entity\CatalogMedia;

final readonly class ProductSearchPicture
{
    public function __construct(
        public string $thumbnailKey,
        public string $cardKey,
        public string $mainKey,
        public string $alt,
        public string $creator,
        public string $sourceUrl,
        public string $license,
        public string $licenseUrl,
        public string $attribution,
    ) {
    }

    public static function fromEntity(CatalogMedia $media): self
    {
        return new self(
            $media->getVariant('thumb')->key, $media->getVariant('card')->key, $media->getVariant('main')->key,
            $media->getAlt(), $media->getCreator(), $media->getSourceUrl(),
            $media->getLicense(), $media->getLicenseUrl(), $media->getAttribution(),
        );
    }

    /** @return array{variants: array{thumb: string, card: string, main: string}, alt: string, creator: string, source_url: string, license: string, license_url: string, attribution: string} */
    public function toArray(): array
    {
        return [
            'variants' => ['thumb' => $this->thumbnailKey, 'card' => $this->cardKey, 'main' => $this->mainKey],
            'alt' => $this->alt, 'creator' => $this->creator, 'source_url' => $this->sourceUrl,
            'license' => $this->license, 'license_url' => $this->licenseUrl, 'attribution' => $this->attribution,
        ];
    }
}
