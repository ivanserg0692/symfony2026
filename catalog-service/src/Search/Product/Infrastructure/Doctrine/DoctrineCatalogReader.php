<?php

namespace App\Search\Product\Infrastructure\Doctrine;

use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Application\Dto\Read\CatalogPage;
use App\Search\Product\Port\Output\CatalogReadInterface;
use App\Repository\CatalogElementsRepository;

final readonly class DoctrineCatalogReader implements CatalogReadInterface
{
    public function __construct(
        private CatalogElementsRepository $repository,
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
        foreach ($this->repository->findListByIds($ids) as $element) {
            $items[] = CatalogElementResponse::fromEntity($element, false);
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

        return $element === null ? null : CatalogElementResponse::fromEntity($element, true);
    }
}
