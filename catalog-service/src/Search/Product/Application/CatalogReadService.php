<?php

namespace App\Search\Product\Application;

use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Application\Dto\Read\CatalogListResponse;
use App\Search\Product\Port\Output\CatalogReadInterface;

final readonly class CatalogReadService
{
    public function __construct(private CatalogReadInterface $catalog)
    {
    }

    public function list(CatalogListCriteria $criteria): CatalogListResponse
    {
        $page = $this->catalog->findPage($criteria);
        $pagination = ["page" => $criteria->page, "limit" => $criteria->limit];
        if ($criteria->lookAhead) {
            $pagination["hasNextPage"] = $page->hasNextPage;
        } else {
            $pagination["total"] = $page->total;
        }

        return new CatalogListResponse($page->items, $pagination);
    }

    public function item(int $id): ?CatalogElementResponse
    {
        return $this->catalog->findById($id);
    }
}
