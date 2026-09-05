<?php

namespace App\Search\Product\Application;

use App\Entity\CatalogElements;
use App\Search\Product\Application\Dto\Document\ProductSearchDocument;
use App\Search\Product\Application\Dto\Indexing\BulkIndexResult;
use App\Search\Product\Application\Dto\Rebuild\ProductSearchReindexProgress;
use App\Search\Product\Application\Dto\Rebuild\ProductSearchReindexResult;
use App\Search\Product\Port\Input\ProductSearchRebuildInterface;
use App\Search\Product\Port\Output\ProductSearchCatalogSourceInterface;
use App\Search\Product\Port\Output\ProductSearchIndexGatewayInterface;
use App\Search\Product\Port\Output\ProductSearchReindexLockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProductSearchRebuildInterface::class)]
final readonly class ProductSearchRebuilder implements ProductSearchRebuildInterface
{
    public function __construct(
        private ProductSearchCatalogSourceInterface $catalogSource,
        private ProductSearchDocumentBuilder $documentBuilder,
        private ProductSearchIndexGatewayInterface $indexGateway,
        private ProductSearchReindexLockInterface $reindexLock,
        private LoggerInterface $logger,
        private int $productSearchBatchSize,
    ) {
        if ($this->productSearchBatchSize < 1) {
            throw new \InvalidArgumentException("Elasticsearch product batch size must be greater than zero.");
        }
    }

    public function countProducts(): int
    {
        return $this->catalogSource->countProducts();
    }

    /**
     * @param null|callable(ProductSearchReindexProgress): void $onProgress
     */
    public function rebuild(?callable $onProgress = null): ProductSearchReindexResult
    {
        if (!$this->reindexLock->acquire()) {
            throw new \RuntimeException("Another Elasticsearch product catalog rebuild is already in progress.");
        }

        try {
            return $this->rebuildWhileLocked($onProgress);
        } finally {
            $this->reindexLock->release();
        }
    }

    /**
     * @param null|callable(ProductSearchReindexProgress): void $onProgress
     */
    private function rebuildWhileLocked(?callable $onProgress): ProductSearchReindexResult
    {
        $startedAt = microtime(true);
        $indexName = $this->indexGateway->createRebuildIndex();

        $this->logger->info("Created Elasticsearch product rebuild index.", ["index" => $indexName]);

        $progress = $this->indexCatalog($indexName, $startedAt, $onProgress);

        return $this->completeRebuild($indexName, $progress, $startedAt);
    }

    /**
     * @param null|callable(ProductSearchReindexProgress): void $onProgress
     */
    private function indexCatalog(
        string $indexName,
        float $startedAt,
        ?callable $onProgress,
    ): ProductSearchReindexProgress {
        $progress = new ProductSearchReindexProgress(0, 0, 0, 0.0);
        $lastId = 0;

        while (true) {
            $ids = $this->catalogSource->findIdsAfter($lastId, $this->productSearchBatchSize);

            if ($ids === []) {
                break;
            }

            $lastId = $ids[array_key_last($ids)];
            $bulkResult = $this->indexBatch($indexName, $ids, $progress);
            $processed = $progress->processed + count($ids);
            $indexed = $progress->indexed + $bulkResult->successful;
            $failed = $progress->failed + $bulkResult->getFailedCount();

            $this->logBulkFailures($indexName, $bulkResult);

            $this->catalogSource->releaseLoadedBatch();

            $progress = new ProductSearchReindexProgress(
                $processed,
                $indexed,
                $failed,
                microtime(true) - $startedAt,
            );

            if ($onProgress !== null) {
                $onProgress($progress);
            }
        }

        return $progress;
    }

    /**
     * @param int[] $ids
     */
    private function indexBatch(
        string $indexName,
        array $ids,
        ProductSearchReindexProgress $progress,
    ): BulkIndexResult {
        try {
            $elements = $this->catalogSource->loadByIds($ids);

            return $this->indexGateway->bulkIndex($indexName, $this->buildDocuments($elements));
        } catch (\Throwable $exception) {
            $this->logger->critical("Elasticsearch product rebuild batch failed.", [
                "index" => $indexName,
                "product_ids" => $ids,
                "processed" => $progress->processed,
                "indexed" => $progress->indexed,
                "failed" => $progress->failed,
                "exception" => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * @param CatalogElements[] $elements
     *
     * @return ProductSearchDocument[]
     */
    private function buildDocuments(array $elements): array
    {
        $documents = [];
        foreach ($elements as $element) {
            $documents[] = $this->documentBuilder->build($element);
        }

        return $documents;
    }

    private function logBulkFailures(string $indexName, BulkIndexResult $bulkResult): void
    {
        foreach ($bulkResult->failures as $failure) {
            $this->logger->error("Elasticsearch product bulk indexing failed.", [
                "product_id" => $failure->productId,
                "error" => $failure->error,
                "index" => $indexName,
            ]);
        }
    }

    private function completeRebuild(
        string $indexName,
        ProductSearchReindexProgress $progress,
        float $startedAt,
    ): ProductSearchReindexResult {
        $elapsedSeconds = microtime(true) - $startedAt;
        if ($progress->failed > 0) {
            $this->logger->error("Elasticsearch product rebuild completed with document failures; alias was not switched.", [
                "index" => $indexName,
                "processed" => $progress->processed,
                "indexed" => $progress->indexed,
                "failed" => $progress->failed,
            ]);

            return new ProductSearchReindexResult(
                $indexName,
                $progress->processed,
                $progress->indexed,
                $progress->failed,
                $elapsedSeconds,
                false,
            );
        }

        $this->indexGateway->refresh($indexName);
        $indexedCount = $this->indexGateway->count($indexName);
        if ($indexedCount !== $progress->indexed) {
            throw new \RuntimeException(sprintf(
                "Elasticsearch rebuild validation failed for %s: expected %d indexed documents, found %d.",
                $indexName,
                $progress->indexed,
                $indexedCount,
            ));
        }

        $this->indexGateway->switchReadAlias($indexName);
        $this->logger->info("Elasticsearch product rebuild completed and alias was switched.", [
            "index" => $indexName,
            "processed" => $progress->processed,
            "indexed" => $progress->indexed,
        ]);

        return new ProductSearchReindexResult(
            $indexName,
            $progress->processed,
            $progress->indexed,
            0,
            $elapsedSeconds,
            true,
        );
    }
}
