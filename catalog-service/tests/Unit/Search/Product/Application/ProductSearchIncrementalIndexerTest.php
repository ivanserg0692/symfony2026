<?php

namespace App\Tests\Unit\Search\Product\Application;

use App\Entity\CatalogElements;
use App\Search\Product\Application\Dto\Indexing\BulkIndexResult;
use App\Search\Product\Application\ProductSearchDocumentBuilder;
use App\Search\Product\Application\ProductSearchIncrementalIndexer;
use App\Search\Product\Application\ProductSearchRebuildInProgressException;
use App\Search\Product\Port\Output\Document\ProductSearchIndexDocumentInterface;
use App\Search\Product\Port\Output\ProductSearchCatalogSourceInterface;
use App\Search\Product\Port\Output\ProductSearchIndexGatewayInterface;
use App\Search\Product\Port\Output\ProductSearchReindexLockInterface;
use PHPUnit\Framework\TestCase;

final class ProductSearchIncrementalIndexerTest extends TestCase
{
    public function testIndexesCurrentProjectionAndRepeatedDeliveryIsIdempotent(): void
    {
        $element = $this->createElement(10, "Current name");
        $source = new IncrementalTestCatalogSource([$element]);
        $gateway = new IncrementalTestIndexGateway();
        $lock = new IncrementalTestReindexLock();
        $indexer = new ProductSearchIncrementalIndexer($source, new ProductSearchDocumentBuilder(), $gateway, $lock);

        $indexer->reindex(10);
        $indexer->reindex(10);

        self::assertCount(2, $gateway->indexedDocuments);
        self::assertSame(
            $gateway->indexedDocuments[0]->toArray(),
            $gateway->indexedDocuments[1]->toArray(),
        );
        self::assertSame("Current name", $gateway->indexedDocuments[1]->toArray()["name"]);
        self::assertSame(2, $source->releaseCount);
        self::assertSame(2, $lock->sharedReleaseCount);
    }

    public function testDeletesDocumentWhenCatalogElementNoLongerExists(): void
    {
        $source = new IncrementalTestCatalogSource([]);
        $gateway = new IncrementalTestIndexGateway();
        $indexer = new ProductSearchIncrementalIndexer(
            $source,
            new ProductSearchDocumentBuilder(),
            $gateway,
            new IncrementalTestReindexLock(),
        );

        $indexer->reindex(404);

        self::assertSame([404], $gateway->deletedIds);
        self::assertSame(1, $source->releaseCount);
    }

    public function testKeepsInactiveProductAndUpdatesItsState(): void
    {
        $element = $this->createElement(10, "Inactive product")->setActive(false);
        $gateway = new IncrementalTestIndexGateway();
        $indexer = new ProductSearchIncrementalIndexer(
            new IncrementalTestCatalogSource([$element]),
            new ProductSearchDocumentBuilder(),
            $gateway,
            new IncrementalTestReindexLock(),
        );

        $indexer->reindex(10);

        self::assertFalse($gateway->indexedDocuments[0]->toArray()["active"]);
        self::assertSame([], $gateway->deletedIds);
    }

    public function testElasticsearchFailureIsPropagatedForMessengerRetry(): void
    {
        $source = new IncrementalTestCatalogSource([$this->createElement(10, "Product")]);
        $gateway = new IncrementalTestIndexGateway();
        $gateway->failure = new \RuntimeException("Elasticsearch unavailable");
        $lock = new IncrementalTestReindexLock();
        $indexer = new ProductSearchIncrementalIndexer($source, new ProductSearchDocumentBuilder(), $gateway, $lock);

        $this->expectExceptionMessage("Elasticsearch unavailable");

        try {
            $indexer->reindex(10);
        } finally {
            self::assertSame(1, $source->releaseCount);
            self::assertSame(1, $lock->sharedReleaseCount);
        }
    }

    public function testDoesNotTouchPostgreSqlOrElasticsearchWhileFullReindexHoldsLock(): void
    {
        $source = new IncrementalTestCatalogSource([$this->createElement(10, "Product")]);
        $gateway = new IncrementalTestIndexGateway();
        $lock = new IncrementalTestReindexLock(false);
        $indexer = new ProductSearchIncrementalIndexer(
            $source,
            new ProductSearchDocumentBuilder(),
            $gateway,
            $lock,
        );

        $this->expectException(ProductSearchRebuildInProgressException::class);

        try {
            $indexer->reindex(10);
        } finally {
            self::assertSame(0, $source->loadCount);
            self::assertSame(0, $source->releaseCount);
            self::assertSame([], $gateway->indexedDocuments);
            self::assertSame([], $gateway->deletedIds);
            self::assertSame(0, $lock->sharedReleaseCount);
        }
    }

    public function testAccumulatedMessagesReadLatestPostgreSqlStateAfterRebuild(): void
    {
        $element = $this->createElement(10, "State after alias switch");
        $source = new IncrementalTestCatalogSource([$element]);
        $gateway = new IncrementalTestIndexGateway();
        $indexer = new ProductSearchIncrementalIndexer(
            $source,
            new ProductSearchDocumentBuilder(),
            $gateway,
            new IncrementalTestReindexLock(),
        );

        foreach ([10, 10, 10] as $accumulatedCatalogElementId) {
            $indexer->reindex($accumulatedCatalogElementId);
        }

        self::assertCount(3, $gateway->indexedDocuments);
        foreach ($gateway->indexedDocuments as $document) {
            self::assertSame("State after alias switch", $document->toArray()["name"]);
        }
    }

    private function createElement(int $id, string $name): CatalogElements
    {
        $element = (new CatalogElements())
            ->setName($name)
            ->setSlug("product-{$id}")
            ->setActive(true)
            ->setCreatedBy(1)
            ->setCreatedAt(new \DateTimeImmutable("2026-09-01T00:00:00+00:00"));
        (new \ReflectionProperty($element, "id"))->setValue($element, $id);
        $element->getProduct()?->setId($id + 1000);

        return $element;
    }
}

final class IncrementalTestCatalogSource implements ProductSearchCatalogSourceInterface
{
    public int $loadCount = 0;
    public int $releaseCount = 0;

    /**
     * @param CatalogElements[] $elements
     */
    public function __construct(
        private readonly array $elements,
    ) {
    }

    public function countProducts(): int
    {
        return count($this->elements);
    }

    public function findIdsAfter(int $lastId, int $limit): array
    {
        return [];
    }

    public function loadByIds(array $ids): array
    {
        ++$this->loadCount;

        return $this->elements;
    }

    public function releaseLoadedBatch(): void
    {
        ++$this->releaseCount;
    }
}

final class IncrementalTestReindexLock implements ProductSearchReindexLockInterface
{
    public int $sharedReleaseCount = 0;

    public function __construct(
        private readonly bool $canAcquireShared = true,
    ) {
    }

    public function acquire(): bool
    {
        return true;
    }

    public function release(): void
    {
    }

    public function acquireShared(): bool
    {
        return $this->canAcquireShared;
    }

    public function releaseShared(): void
    {
        ++$this->sharedReleaseCount;
    }
}

final class IncrementalTestIndexGateway implements ProductSearchIndexGatewayInterface
{
    /** @var ProductSearchIndexDocumentInterface[] */
    public array $indexedDocuments = [];

    /** @var int[] */
    public array $deletedIds = [];

    public ?\Throwable $failure = null;

    public function createRebuildIndex(): string
    {
        return "unused";
    }

    public function bulkIndex(string $indexName, array $documents): BulkIndexResult
    {
        return new BulkIndexResult(count($documents), []);
    }

    public function indexInCurrentIndex(ProductSearchIndexDocumentInterface $document): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->indexedDocuments[] = $document;
    }

    public function deleteFromCurrentIndex(int $catalogElementId): void
    {
        $this->deletedIds[] = $catalogElementId;
    }

    public function refresh(string $indexName): void
    {
    }

    public function count(string $indexName): int
    {
        return 0;
    }

    public function switchReadAlias(string $targetIndexName): void
    {
    }
}
