<?php

namespace App\DataFixtures\Media;

final readonly class FixtureMediaAsset
{
    /** @param list<string> $categorySlugs @param array<string, FixtureMediaFile> $variants */
    public function __construct(
        public string $id,
        public array $categorySlugs,
        public string $productType,
        public string $alt,
        public FixtureMediaFile $source,
        public array $variants,
        public string $creator,
        public string $sourceUrl,
        public string $license,
        public string $licenseUrl,
        public string $attribution,
    ) {
        foreach (['thumb', 'card', 'main'] as $variant) {
            if (!isset($variants[$variant])) {
                throw new \InvalidArgumentException(sprintf('Missing %s variant for %s.', $variant, $id));
            }
        }
        if ($categorySlugs === [] || $productType === '') {
            throw new \InvalidArgumentException('A catalog image needs categories and a product type.');
        }
    }

    public function pictureId(): string
    {
        return $this->variants['main']->key;
    }
}
