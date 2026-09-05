<?php

namespace App\Search\Product\Application;

use App\Search\Product\Port\Input\ProductSearchIncrementalIndexInterface;
use App\Search\Product\Port\Output\ProductSearchCatalogSourceInterface;
use App\Search\Product\Port\Output\ProductSearchIndexGatewayInterface;
use App\Search\Product\Port\Output\ProductSearchReindexLockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProductSearchIncrementalIndexInterface::class)]
final readonly class ProductSearchIncrementalIndexer implements ProductSearchIncrementalIndexInterface
{
    public function __construct(
        private ProductSearchCatalogSourceInterface $catalogSource,
        private ProductSearchDocumentBuilder $documentBuilder,
        private ProductSearchIndexGatewayInterface $indexGateway,
        private ProductSearchReindexLockInterface $reindexLock,
    ) {
    }

    public function reindex(int $catalogElementId): void
    {
        if (!$this->reindexLock->acquireShared()) {
            throw new ProductSearchRebuildInProgressException();
        }

        try {
            try {
                $elements = $this->catalogSource->loadByIds([$catalogElementId]);

                if ($elements === []) {
                    $this->indexGateway->deleteFromCurrentIndex($catalogElementId);

                    return;
                }

                $this->indexGateway->indexInCurrentIndex(
                    $this->documentBuilder->build($elements[0]),
                );
            } finally {
                $this->catalogSource->releaseLoadedBatch();
            }
        } finally {
            $this->reindexLock->releaseShared();
        }
    }
}
