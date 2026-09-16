<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSectionAggregationQueryBuilder;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchGateway;
use App\Search\Product\Port\Output\CatalogSectionReadInterface;

final readonly class ElasticsearchCatalogSectionReader implements CatalogSectionReadInterface
{
    public function __construct(
        private CatalogSearchGateway $searchGateway,
        private CatalogSectionAggregationQueryBuilder $queryBuilder,
    ) {
    }

    public function findActive(): array
    {
        $pit = $this->searchGateway->openPointInTime();
        $afterKey = null;
        $sections = [];

        try {
            do {
                $response = $this->searchGateway->searchSectionPage($this->queryBuilder->activePage($pit, $afterKey));
                $response->assertComplete();
                $pit = $response->pitId() ?? $pit;
                $pageSections = $response->activeSections();
                foreach ($pageSections as $section) {
                    $sections[] = $section;
                }
                $afterKey = $response->nextPageKey();
            } while ($pageSections !== [] && $afterKey !== null);
        } finally {
            $this->searchGateway->closePointInTime($pit);
        }

        usort($sections, static fn(CatalogSectionResponse $a, CatalogSectionResponse $b): int =>
            ($b->sort ?? 0) <=> ($a->sort ?? 0) ?: $a->id <=> $b->id);

        return $sections;
    }

    public function findById(int $id): ?CatalogSectionResponse
    {
        $response = $this->searchGateway->searchSectionById($this->queryBuilder->byId($id));
        $response->assertComplete();

        return $response->section();
    }
}
