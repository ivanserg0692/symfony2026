<?php

namespace App\Tests\Unit\Search\Product\Application;

use App\Entity\CatalogElements;
use App\Search\Product\Application\Dto\Indexing\BulkIndexFailure;
use App\Search\Product\Application\Dto\Indexing\BulkIndexResult;
use App\Search\Product\Application\ProductSearchDocumentBuilder;
use App\Search\Product\Application\ProductSearchRebuilder;
use App\Search\Product\Port\Input\ProductSearchRebuildInterface;
use App\Search\Product\Port\Output\ProductSearchCatalogSourceInterface;
use App\Search\Product\Port\Output\ProductSearchIndexGatewayInterface;
use App\Search\Product\Port\Output\ProductSearchReindexLockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProductSearchRebuilderTest extends TestCase
{
    public function testImplementsInputPort(): void
    {
        self::assertTrue(is_a(
            ProductSearchRebuilder::class,
            ProductSearchRebuildInterface::class,
            true,
        ));
    }

    public function testDoesNotSwitchAliasWhenBulkContainsDocumentFailure(): void
    {
        $gateway = new TestProductSearchIndexGateway(
            new BulkIndexResult(0, [new BulkIndexFailure(10, "mapping failed")]),
            0,
        );

        $result = $this->createRebuilder($gateway)->rebuild();

        self::assertSame(1, $result->processed);
        self::assertSame(0, $result->indexed);
        self::assertSame(1, $result->failed);
        self::assertFalse($result->aliasSwitched);
        self::assertFalse($gateway->aliasSwitched);
        self::assertFalse($gateway->refreshed);
    }

    public function testRefreshesValidatesAndAtomicallySwitchesAliasAfterSuccess(): void
    {
        $gateway = new TestProductSearchIndexGateway(new BulkIndexResult(1, []), 1);

        $result = $this->createRebuilder($gateway)->rebuild();

        self::assertTrue($result->aliasSwitched);
        self::assertTrue($gateway->refreshed);
        self::assertTrue($gateway->aliasSwitched);
        self::assertSame($result->indexName, $gateway->targetIndexName);
    }

    public function testDoesNotSwitchAliasWhenIndexedCountValidationFails(): void
    {
        $gateway = new TestProductSearchIndexGateway(new BulkIndexResult(1, []), 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("validation failed");

        try {
            $this->createRebuilder($gateway)->rebuild();
        } finally {
            self::assertFalse($gateway->aliasSwitched);
        }
    }

    public function testReleasesReindexLockAfterFailure(): void
    {
        $gateway = new TestProductSearchIndexGateway(new BulkIndexResult(1, []), 0);
        $lock = new TestProductSearchReindexLock();

        try {
            $this->createRebuilder($gateway, $lock)->rebuild();
            self::fail("Expected rebuild validation failure.");
        } catch (\RuntimeException) {
            self::assertFalse($lock->acquired);
            self::assertSame(1, $lock->releaseCount);
        }
    }

    public function testRejectsConcurrentFullReindex(): void
    {
        $lock = new TestProductSearchReindexLock(false);
        $catalogSource = $this->createStub(ProductSearchCatalogSourceInterface::class);
        $rebuilder = new ProductSearchRebuilder(
            $catalogSource,
            new ProductSearchDocumentBuilder(),
            new TestProductSearchIndexGateway(new BulkIndexResult(0, []), 0),
            $lock,
            new NullLogger(),
            500,
        );

        $this->expectExceptionMessage("already in progress");

        $rebuilder->rebuild();
    }

    private function createRebuilder(
        TestProductSearchIndexGateway $gateway,
        ?TestProductSearchReindexLock $lock = null,
    ): ProductSearchRebuilder
    {
        $element = (new CatalogElements())
            ->setName("Product")
            ->setSlug("product")
            ->setActive(true)
            ->setCreatedBy(1)
            ->setCreatedAt(new \DateTimeImmutable("2026-09-01T00:00:00+00:00"));
        (new \ReflectionProperty($element, "id"))->setValue($element, 10);
        $element->getProduct()?->setId(110);

        $catalogSource = $this->createMock(ProductSearchCatalogSourceInterface::class);
        $catalogSource
            ->expects(self::exactly(2))
            ->method("findIdsAfter")
            ->willReturnOnConsecutiveCalls([10], []);
        $catalogSource
            ->expects(self::once())
            ->method("loadByIds")
            ->with([10])
            ->willReturn([$element]);
        $catalogSource->expects(self::once())->method("releaseLoadedBatch");

        return new ProductSearchRebuilder(
            $catalogSource,
            new ProductSearchDocumentBuilder(),
            $gateway,
            $lock ?? new TestProductSearchReindexLock(),
            new NullLogger(),
            500,
        );
    }
}

final class TestProductSearchIndexGateway implements ProductSearchIndexGatewayInterface
{
    public bool $refreshed = false;
    public bool $aliasSwitched = false;
    public ?string $targetIndexName = null;

    public function __construct(
        private readonly BulkIndexResult $bulkResult,
        private readonly int $indexedCount,
    ) {
    }

    public function createRebuildIndex(): string
    {
        return "products-v1-test";
    }

    public function bulkIndex(string $indexName, array $documents): BulkIndexResult
    {
        return $this->bulkResult;
    }

    public function indexInCurrentIndex(\App\Search\Product\Port\Output\Document\ProductSearchIndexDocumentInterface $document): void
    {
    }

    public function deleteFromCurrentIndex(int $catalogElementId): void
    {
    }

    public function refresh(string $indexName): void
    {
        $this->refreshed = true;
    }

    public function count(string $indexName): int
    {
        return $this->indexedCount;
    }

    public function switchReadAlias(string $targetIndexName): void
    {
        $this->aliasSwitched = true;
        $this->targetIndexName = $targetIndexName;
    }
}

final class TestProductSearchReindexLock implements ProductSearchReindexLockInterface
{
    public bool $acquired = false;
    public bool $sharedAcquired = false;
    public int $releaseCount = 0;
    public int $sharedReleaseCount = 0;

    public function __construct(
        private readonly bool $canAcquire = true,
    ) {
    }

    public function acquire(): bool
    {
        $this->acquired = $this->canAcquire;

        return $this->canAcquire;
    }

    public function release(): void
    {
        $this->acquired = false;
        ++$this->releaseCount;
    }

    public function acquireShared(): bool
    {
        $this->sharedAcquired = $this->canAcquire;

        return $this->canAcquire;
    }

    public function releaseShared(): void
    {
        $this->sharedAcquired = false;
        ++$this->sharedReleaseCount;
    }
}
