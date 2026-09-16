<?php

namespace App\Search\Product\Application;

use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;
use App\Search\Product\Port\Input\CatalogSectionReadInputInterface;
use App\Search\Product\Port\Output\CatalogSectionReadInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CatalogSectionReadInputInterface::class)]
final readonly class CatalogSectionReadService implements CatalogSectionReadInputInterface
{
    public function __construct(private CatalogSectionReadInterface $sections)
    {
    }

    /** @return list<CatalogSectionResponse> */
    public function list(): array
    {
        return $this->sections->findActive();
    }

    public function item(int $id): ?CatalogSectionResponse
    {
        return $this->sections->findById($id);
    }
}
