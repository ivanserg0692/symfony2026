<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Infrastructure\CatalogResponseFactory;
use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Application\Dto\Read\CatalogPage;
use App\Search\Product\Port\Output\CatalogReadInterface;

final readonly class ElasticsearchCatalogReader implements CatalogReadInterface
{
    public function __construct(
        private ProductSearchRepository $repository,
        private CatalogResponseFactory $responses,
    ) {
    }

    public function findPage(CatalogListCriteria $criteria): CatalogPage
    {
        $result = $this->repository->findPage($criteria);

        $hits = $result->hits();
        $hasNextPage = $criteria->lookAhead && count($hits) > $criteria->limit;
        $items = [];
        // lookAhead fetches one extra hit to detect the next page; it is not part of the response.
        foreach (array_slice($hits, 0, $criteria->limit) as $hit) {
            $items[] = $this->responses->elementFromDocument($hit->source());
        }

        return new CatalogPage(
            $items,
            $criteria->lookAhead ? null : $result->exactTotal(),
            $hasNextPage,
        );
    }

    public function findById(int $id): ?CatalogElementResponse
    {
        $document = $this->repository->findById($id);

        if ($document === null) {
            return null;
        }
        $source = $document->source();
        return $this->responses->elementFromDocument($source);
    }
}
