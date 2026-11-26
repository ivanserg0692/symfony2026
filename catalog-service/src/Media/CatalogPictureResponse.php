<?php

namespace App\Media;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class CatalogPictureResponse
{
    public function __construct(
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        #[OA\Property(format: 'uri')] public string $thumbnailUrl,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        #[OA\Property(format: 'uri')] public string $cardUrl,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        #[OA\Property(format: 'uri')] public string $fullUrl,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        public string $alt,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        public string $creator,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        #[OA\Property(format: 'uri')] public string $sourceUrl,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        public string $license,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        #[OA\Property(format: 'uri')] public string $licenseUrl,
        #[Groups(['catalog_element:list', 'catalog_element:item', 'catalog_section:list', 'catalog_section:item', 'product_snapshot:list', 'product_snapshot:item'])]
        public string $attribution,
    ) {
    }
}
