<?php

namespace App\DataFixtures\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FixtureMediaLibrary
{
    /** @var array<string, FixtureMediaAsset>|null */
    private ?array $assets = null;
    /** @var array<string, list<FixtureMediaAsset>> */
    private array $categories = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/fixtures/media')]
        private readonly string $directory,
    ) {
    }

    /** @return array<string, FixtureMediaAsset> Indexed by pictureId. */
    public function assets(): array
    {
        if ($this->assets !== null) {
            return $this->assets;
        }
        $path = $this->directory.'/manifest.json';
        if (!is_file($path)) {
            throw new \RuntimeException('Catalog media manifest is missing; run npm run catalog:media:prepare.');
        }
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['schemaVersion'] ?? null) !== 1 || ($manifest['assets'] ?? []) === []) {
            throw new \RuntimeException('Missing or unsupported catalog media manifest.');
        }
        $assets = [];
        $active = array_fill_keys(array_map(static fn (array $row): string => $row['variants']['main']['key'], $manifest['assets']), true);
        foreach ([...$manifest['assets'], ...($manifest['retiredAssets'] ?? [])] as $row) {
            $source = $this->fileFromManifest($row['source']);
            $variants = [];
            foreach ($row['variants'] as $name => $file) {
                $variants[$name] = $this->fileFromManifest($file);
            }
            $asset = new FixtureMediaAsset(
                $row['assetId'], $row['categorySlugs'], $row['productType'], $row['alt'],
                $source, $variants, $row['creator'], $row['sourceUrl'],
                $row['license'], $row['licenseUrl'], $row['attribution'],
            );
            if (isset($assets[$asset->pictureId()])) {
                throw new \RuntimeException('Duplicate pictureId in catalog media manifest.');
            }
            $assets[$asset->pictureId()] = $asset;
            if (isset($active[$asset->pictureId()])) {
                foreach ($asset->categorySlugs as $slug) {
                    $this->categories[$slug][] = $asset;
                }
            }
        }
        return $this->assets = $assets;
    }

    public function find(?string $pictureId): ?FixtureMediaAsset
    {
        if ($pictureId === null || !preg_match('~^catalog/demo/v1/[a-z0-9-]+/[a-f0-9]{16}/main\.webp$~D', $pictureId)) {
            return null;
        }
        return $this->assets()[$pictureId] ?? null;
    }

    public function forCategory(string $slug, int $ordinal): FixtureMediaAsset
    {
        if ($ordinal < 1) {
            throw new \InvalidArgumentException('A fixture product ordinal must be positive.');
        }
        $this->assets();
        $assets = $this->categories[$slug] ?? [];
        if ($assets === []) {
            throw new \RuntimeException(sprintf('No catalog photographs for category %s.', $slug));
        }
        return $assets[($ordinal - 1) % count($assets)];
    }

    public function forSection(string $slug): FixtureMediaAsset
    {
        $this->assets();
        foreach ($this->categories as $category => $assets) {
            if ($category === $slug || str_starts_with($category, $slug.'-')) {
                return $assets[0];
            }
        }
        throw new \RuntimeException(sprintf('No catalog photographs for section %s.', $slug));
    }

    public function absolutePath(FixtureMediaFile $file): string
    {
        $path = realpath($this->directory.'/'.$file->localPath);
        if ($path === false || !str_starts_with($path, realpath($this->directory).DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Catalog media file is missing or outside the library.');
        }
        return $path;
    }

    public function validateFiles(): void
    {
        foreach ($this->assets() as $asset) {
            foreach ([$asset->source, ...array_values($asset->variants)] as $file) {
                if (hash_file('sha256', $this->absolutePath($file)) !== $file->sha256) {
                    throw new \RuntimeException(sprintf('Checksum mismatch for %s.', $file->localPath));
                }
            }
        }
    }

    /** @param array{path: string, key: string, sha256: string, contentType: string} $file */
    private function fileFromManifest(array $file): FixtureMediaFile
    {
        return new FixtureMediaFile($file['path'], $file['key'], $file['sha256'], $file['contentType']);
    }
}
