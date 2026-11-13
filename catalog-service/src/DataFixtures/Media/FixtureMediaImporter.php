<?php

namespace App\DataFixtures\Media;

use App\Entity\CatalogMedia;
use App\Media\CatalogMediaStorage;
use App\Media\CatalogMediaVariant;
use App\Repository\CatalogMediaRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FixtureMediaImporter
{
    public function __construct(
        private FixtureMediaLibrary $library,
        private CatalogMediaStorage $storage,
        private CatalogMediaRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** Upload objects before registering them, so failed uploads never publish incomplete metadata. */
    public function import(): int
    {
        $this->library->validateFiles();
        $this->storage->ensureBuckets();
        $uploaded = 0;
        foreach ($this->library->assets() as $asset) {
            foreach ([$asset->source, ...array_values($asset->variants)] as $file) {
                $uploaded += (int) $this->storage->uploadOnce(
                    $file->key, $this->library->absolutePath($file), $file->sha256,
                    $file->contentType, $file === $asset->source,
                );
            }
        }
        $this->entityManager->getConnection()->transactional(function (): void {
            foreach ($this->library->assets() as $asset) {
                $variant = static fn (string $name): CatalogMediaVariant => new CatalogMediaVariant($asset->variants[$name]->key, $asset->variants[$name]->sha256);
                $this->repository->register(new CatalogMedia(
                    $asset->pictureId(), $variant('thumb'), $variant('card'), $variant('main'),
                    $asset->alt, $asset->creator, $asset->sourceUrl,
                    $asset->license, $asset->licenseUrl, $asset->attribution,
                ));
            }
        });
        return $uploaded;
    }
}
