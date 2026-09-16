<?php

namespace App\Search\Product\Infrastructure;

use App\Search\Product\Infrastructure\Doctrine\DoctrineCatalogSectionReader;
use App\Search\Product\Infrastructure\Elasticsearch\ElasticsearchCatalogSectionReader;
use App\Search\Product\Port\Output\CatalogSectionReadInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

final readonly class CatalogSectionReadFactory
{
    /**
     * @param \Closure(): CatalogSectionReadInterface $doctrine
     * @param \Closure(): CatalogSectionReadInterface $elasticsearch
     */
    public function __construct(
        private string $catalogReadModel,
        #[AutowireServiceClosure(DoctrineCatalogSectionReader::class)]
        private \Closure $doctrine,
        #[AutowireServiceClosure(ElasticsearchCatalogSectionReader::class)]
        private \Closure $elasticsearch,
    ) {
    }

    public function create(): CatalogSectionReadInterface
    {
        return match ($this->catalogReadModel) {
            "doctrine" => ($this->doctrine)(),
            "elasticsearch" => ($this->elasticsearch)(),
            default => throw new \InvalidArgumentException("CATALOG_READ_MODEL must be doctrine or elasticsearch."),
        };
    }
}
