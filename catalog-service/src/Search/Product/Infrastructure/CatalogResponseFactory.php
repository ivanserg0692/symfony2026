<?php

namespace App\Search\Product\Infrastructure;

use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Media\Infrastructure\CatalogPictureResponseFactory;
use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogPriceResponse;
use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;

final readonly class CatalogResponseFactory
{
    public function __construct(private CatalogPictureResponseFactory $pictures)
    {
    }

    public function elementFromEntity(CatalogElements $element, bool $includeSections): CatalogElementResponse
    {
        $picture = $element->getProduct()?->getPicture();

        return new CatalogElementResponse(
            $element->getId(), $element->getName(), $element->isActive(),
            $element->getDescription(), $element->getSlug(),
            $element->getSort(),
            array_values(array_map(CatalogPriceResponse::fromEntity(...), $element->getProductPrices()->toArray())),
            $element->getTotalStock(),
            $includeSections
                ? array_values(array_map(fn ($section) => $this->sectionFromEntity($section), $element->getSections()->toArray()))
                : [],
            $picture === null ? null : $this->pictures->fromEntity($picture),
        );
    }

    /** @param array<string, mixed> $document */
    public function elementFromDocument(array $document): CatalogElementResponse
    {
        return new CatalogElementResponse(
            $document["id"], $document["name"], $document["active"],
            $document["description"], $document["slug"],
            $document["sort"],
            array_map(CatalogPriceResponse::fromDocument(...), $document["prices"]),
            $document["total_stock"],
            array_map(fn (array $section) => $this->sectionFromDocument($section), $document["sections"]),
            $document['picture'] === null ? null : $this->pictures->fromDocument($document['picture']),
        );
    }

    public function sectionFromEntity(CatalogSections $section): CatalogSectionResponse
    {
        $picture = $section->getPicture();

        return new CatalogSectionResponse(
            $section->getId(), $section->getName(), $section->getSlug(),
            $section->isActive(), $section->getDescription(),
            $section->getLevel(), $section->getSort(), $section->getParentId(),
            $picture === null ? null : $this->pictures->fromEntity($picture),
        );
    }

    /** @param array<string, mixed> $section */
    public function sectionFromDocument(array $section): CatalogSectionResponse
    {
        return new CatalogSectionResponse(
            $section["id"], $section["name"], $section["slug"], $section["active"],
            $section["description"], $section["level"],
            $section["sort"], $section["parent_id"],
            $section['picture'] === null ? null : $this->pictures->fromDocument($section['picture']),
        );
    }
}
