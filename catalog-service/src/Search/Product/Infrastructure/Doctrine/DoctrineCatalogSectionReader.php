<?php

namespace App\Search\Product\Infrastructure\Doctrine;

use App\Repository\CatalogSectionsRepository;
use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;
use App\Search\Product\Port\Output\CatalogSectionReadInterface;

final readonly class DoctrineCatalogSectionReader implements CatalogSectionReadInterface
{
    public function __construct(private CatalogSectionsRepository $repository)
    {
    }

    public function findActive(): array
    {
        return array_map(
            CatalogSectionResponse::fromEntity(...),
            $this->repository->findActiveForPublicList(),
        );
    }

    public function findById(int $id): ?CatalogSectionResponse
    {
        $section = $this->repository->findOneForPublicApi($id);

        return $section === null ? null : CatalogSectionResponse::fromEntity($section);
    }
}
