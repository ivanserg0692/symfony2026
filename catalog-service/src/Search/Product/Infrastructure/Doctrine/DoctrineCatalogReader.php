<?php

namespace App\Search\Product\Infrastructure\Doctrine;

use App\Search\Product\Infrastructure\CatalogResponseFactory;
use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Application\Dto\Read\CatalogPage;
use App\Search\Product\Port\Output\CatalogReadInterface;
use App\Repository\CatalogElementsRepository;

final readonly class DoctrineCatalogReader implements CatalogReadInterface
{
    public function __construct(
        private CatalogElementsRepository $repository,
        private CatalogResponseFactory $responses,
    ) {
    }

    public function findPage(CatalogListCriteria $criteria): CatalogPage
    {
        $ids = $this->repository->findPageIds(
            $criteria->sectionId, $criteria->active, $criteria->page,
            $criteria->limit, $criteria->lookAhead,
        );
        $hasNextPage = $criteria->lookAhead && count($ids) > $criteria->limit;
        if ($hasNextPage) {
            $ids = array_slice($ids, 0, $criteria->limit);
        }
        $items = [];
        $elements = $this->repository->findListByIds($ids);
        foreach ($elements as $element) {
            $items[] = $this->responses->elementFromEntity($element, false);
        }

        return new CatalogPage(
            $items,
            $criteria->lookAhead ? null : $this->repository->countMatchingListFilters($criteria->sectionId, $criteria->active),
            $hasNextPage,
        );
    }

    public function findById(int $id): ?CatalogElementResponse
    {
        $element = $this->repository->findOneForPublicApi($id);

        if ($element === null) {
            return null;
        }
        return $this->responses->elementFromEntity($element, true);
    }
}
