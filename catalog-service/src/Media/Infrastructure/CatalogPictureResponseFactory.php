<?php

namespace App\Media\Infrastructure;

use App\Entity\CatalogMedia;
use App\Media\CatalogPictureResponse;

final readonly class CatalogPictureResponseFactory
{
    private string $publicBaseUrl;

    public function __construct(string $catalogMediaPublicBaseUrl)
    {
        $this->publicBaseUrl = rtrim($catalogMediaPublicBaseUrl, '/') . '/';
    }

    public function fromEntity(CatalogMedia $media): CatalogPictureResponse
    {
        return new CatalogPictureResponse(
            $this->publicBaseUrl . $media->getVariant('thumb')->key,
            $this->publicBaseUrl . $media->getVariant('card')->key,
            $this->publicBaseUrl . $media->getVariant('main')->key,
            $media->getAlt(), $media->getCreator(), $media->getSourceUrl(),
            $media->getLicense(), $media->getLicenseUrl(), $media->getAttribution(),
        );
    }

    /** @param array<string, mixed> $picture */
    public function fromDocument(array $picture): CatalogPictureResponse
    {
        return new CatalogPictureResponse(
            $this->publicBaseUrl . $picture['variants']['thumb'],
            $this->publicBaseUrl . $picture['variants']['card'],
            $this->publicBaseUrl . $picture['variants']['main'],
            $picture['alt'], $picture['creator'], $picture['source_url'],
            $picture['license'], $picture['license_url'], $picture['attribution'],
        );
    }
}
