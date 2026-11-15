<?php

namespace App\DataFixtures\Media;

use App\Media\Infrastructure\CatalogMediaStorage;

final readonly class FixtureMediaImporter
{
    public function __construct(
        private FixtureMediaLibrary $library,
        private CatalogMediaStorage $storage,
    ) {
    }

    /** Only immutable fixture files are shared; owner fixtures persist their own metadata. */
    public function import(): int
    {
        $this->library->validateFiles();
        $this->storage->ensureBucket();
        $uploaded = 0;
        foreach ($this->library->assets() as $asset) {
            foreach ($asset->variants as $file) {
                $uploaded += (int) $this->storage->uploadOnce(
                    $file->key, $this->library->absolutePath($file), $file->sha256,
                    $file->contentType,
                );
            }
        }
        return $uploaded;
    }
}
