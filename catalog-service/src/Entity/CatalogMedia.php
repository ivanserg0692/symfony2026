<?php

namespace App\Entity;

use App\Media\CatalogMediaVariant;
use App\Repository\CatalogMediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CatalogMediaRepository::class)]
#[ORM\Table(name: 'catalog_media')]
#[ORM\Index(name: 'catalog_media_picture_idx', columns: ['picture_id'])]
class CatalogMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    /** @var array<string, array{key: string, sha256: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $variants;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $pictureId,
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
        if ($pictureId !== $main->key) {
            throw new \InvalidArgumentException('pictureId must identify the main variant.');
        }
        $this->variants = [
            'thumb' => ['key' => $thumbnail->key, 'sha256' => $thumbnail->sha256],
            'card' => ['key' => $card->key, 'sha256' => $card->sha256],
            'main' => ['key' => $main->key, 'sha256' => $main->sha256],
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** A new owner gets independent metadata while immutable file objects may be shared. */
    public function copy(): self
    {
        return new self(
            $this->pictureId, $this->getVariant('thumb'), $this->getVariant('card'), $this->getVariant('main'),
            $this->alt, $this->creator, $this->sourceUrl, $this->license, $this->licenseUrl, $this->attribution,
        );
    }

    public function getVariant(string $name): CatalogMediaVariant
    {
        $variant = $this->variants[$name] ?? throw new \InvalidArgumentException('Unknown media variant.');
        return new CatalogMediaVariant($variant['key'], $variant['sha256']);
    }

    public function getPictureId(): string
    {
        return $this->pictureId;
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
