<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchGateway;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchHit;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchQueryBuilder;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchResponse;

/**
 * Product-specific Elasticsearch repository.
 *
 * Transport access and query construction stay behind CatalogSearchGateway and
 * CatalogSearchQueryBuilder; this class owns product search semantics such as
 * deep pagination and PIT lifecycle.
 */
final readonly class ProductSearchRepository
{
    public function __construct(
        private CatalogSearchGateway $searchGateway,
        private CatalogSearchQueryBuilder $queryBuilder,
    ) {
    }

    public function findPage(CatalogListCriteria $criteria): CatalogSearchResponse
    {
        if (!$this->queryBuilder->requiresDeepPagination($criteria)) {
            $result = $this->searchGateway->search(
                $this->queryBuilder->buildOffsetPage($criteria),
            );
            $result->assertComplete();

            return $result;
        }

        return $this->deepPage($criteria);
    }

    public function findById(int $id): ?CatalogSearchHit
    {
        return $this->searchGateway->findById($id);
    }

    private function deepPage(CatalogListCriteria $criteria): CatalogSearchResponse
    {
        $pit = $this->searchGateway->openPointInTime();
        $remaining = $criteria->getOffset();
        $total = null;

        try {
            $searchAfter = null;
            while (true) {
                $pageSize = $this->queryBuilder->deepPageSize($criteria, $remaining);
                $body = $this->queryBuilder->buildDeepPage(
                    $criteria,
                    $pit,
                    $remaining,
                    $searchAfter,
                    $remaining === 0,
                    !$criteria->lookAhead && $total === null,
                );
                $result = $this->searchGateway->searchPointInTime($body);
                $pit = $result->pitId() ?? $pit;
                $result->assertComplete();
                if (!$criteria->lookAhead && $total === null) {
                    $total = $result->exactTotal();
                }
                $hits = $result->hits();

                // A short batch before the requested offset means the index ended early.
                // The requested page is therefore outside the available result set.
                if ($remaining === 0 || count($hits) < $pageSize) {
                    if ($remaining > 0) {
                        $result = $result->withHits([]);
                    }
                    if ($total !== null) {
                        $result = $result->withExactTotal($total);
                    }

                    return $result;
                }
                $remaining -= count($hits);
                $searchAfter = $hits[array_key_last($hits)]->sortValues();
            }
        } finally {
            $this->searchGateway->closePointInTime($pit);
        }
    }
}
