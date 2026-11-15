<?php

namespace App\Entity;

use App\Media\CatalogMediaVariant;
use App\Repository\CatalogMediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CatalogMediaRepository::class)]
#[ORM\Table(name: 'catalog_media')]
#[ORM\Index(name: 'catalog_media_thumbnail_idx', columns: ['thumbnail_key'])]
#[ORM\Index(name: 'catalog_media_card_idx', columns: ['card_key'])]
#[ORM\Index(name: 'catalog_media_main_idx', columns: ['main_key'])]
class CatalogMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $thumbnailKey;

    #[ORM\Column(length: 64)]
    private string $thumbnailSha256;

    #[ORM\Column(length: 255)]
    private string $cardKey;

    #[ORM\Column(length: 64)]
    private string $cardSha256;

    #[ORM\Column(length: 255)]
    private string $mainKey;

    #[ORM\Column(length: 64)]
    private string $mainSha256;

    public function __construct(
        CatalogMediaVariant $thumbnail,
        CatalogMediaVariant $card,
        CatalogMediaVariant $main,
        #[ORM\Column(type: Types::TEXT)]
        private string $alt,
        #[ORM\Column(type: Types::TEXT)]
        private string $creator,
        #[ORM\Column(type: Types::TEXT)]
        private string $sourceUrl,
        #[ORM\Column(type: Types::TEXT)]
        private string $license,
        #[ORM\Column(type: Types::TEXT)]
        private string $licenseUrl,
        #[ORM\Column(type: Types::TEXT)]
        private string $attribution,
    ) {
        $this->thumbnailKey = $thumbnail->key;
        $this->thumbnailSha256 = $thumbnail->sha256;
        $this->cardKey = $card->key;
        $this->cardSha256 = $card->sha256;
        $this->mainKey = $main->key;
        $this->mainSha256 = $main->sha256;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** A new owner gets independent metadata while immutable file objects may be shared. */
    public function copy(): self
    {
        return new self(
            $this->getVariant('thumb'), $this->getVariant('card'), $this->getVariant('main'),
            $this->alt, $this->creator, $this->sourceUrl, $this->license, $this->licenseUrl, $this->attribution,
        );
    }

    public function getVariant(string $name): CatalogMediaVariant
    {
        return match ($name) {
            'thumb' => new CatalogMediaVariant($this->thumbnailKey, $this->thumbnailSha256),
            'card' => new CatalogMediaVariant($this->cardKey, $this->cardSha256),
            'main' => new CatalogMediaVariant($this->mainKey, $this->mainSha256),
            default => throw new \InvalidArgumentException('Unknown media variant.'),
        };
    }

    public function getVariantByKey(string $key): ?CatalogMediaVariant
    {
        return match ($key) {
            $this->thumbnailKey => $this->getVariant('thumb'),
            $this->cardKey => $this->getVariant('card'),
            $this->mainKey => $this->getVariant('main'),
            default => null,
        };
    }

    /** Main object key retained for the existing gRPC snapshot contract. */
    public function getPictureId(): string
    {
        return $this->mainKey;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getCreator(): string
    {
        return $this->creator;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getLicense(): string
    {
        return $this->license;
    }

    public function getLicenseUrl(): string
    {
        return $this->licenseUrl;
    }

    public function getAttribution(): string
    {
        return $this->attribution;
    }
}
