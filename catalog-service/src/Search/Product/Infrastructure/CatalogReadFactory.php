<?php

namespace App\Search\Product\Infrastructure;

use App\Search\Product\Infrastructure\Doctrine\DoctrineCatalogReader;
use App\Search\Product\Infrastructure\Elasticsearch\ElasticsearchCatalogReader;
use App\Search\Product\Port\Output\CatalogReadInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

final readonly class CatalogReadFactory
{
    /**
     * @param \Closure(): CatalogReadInterface $doctrine
     * @param \Closure(): CatalogReadInterface $elasticsearch
     */
    public function __construct(
        private string $catalogReadModel,
        #[AutowireServiceClosure(DoctrineCatalogReader::class)]
        private \Closure $doctrine,
        #[AutowireServiceClosure(ElasticsearchCatalogReader::class)]
        private \Closure $elasticsearch,
    ) {
    }

    public function create(): CatalogReadInterface
    {
        return match ($this->catalogReadModel) {
            "doctrine" => ($this->doctrine)(),
            "elasticsearch" => ($this->elasticsearch)(),
            default => throw new \InvalidArgumentException(
                "CATALOG_READ_MODEL must be doctrine or elasticsearch.",
            ),
        };
    }
}
