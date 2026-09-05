<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Messenger;

use App\Search\Product\Application\ProductSearchRebuildInProgress;
use App\Search\Product\Infrastructure\Messenger\ProductSearchReindexMessage;
use App\Search\Product\Infrastructure\Messenger\ProductSearchReindexMessageHandler;
use App\Search\Product\Port\Input\ProductSearchIncrementalIndexInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final class ProductSearchReindexMessageHandlerTest extends TestCase
{
    public function testDelegatesCatalogElementIdToIncrementalIndexInputPort(): void
    {
        $incrementalIndexer = new TestProductSearchIncrementalIndexer();
        $handler = new ProductSearchReindexMessageHandler($incrementalIndexer);

        $handler(new ProductSearchReindexMessage(42));

        self::assertSame([42], $incrementalIndexer->catalogElementIds);
    }

    public function testDefersMessageWhenFullReindexIsInProgress(): void
    {
        $handler = new ProductSearchReindexMessageHandler(
            new TestProductSearchIncrementalIndexer(true),
        );

        try {
            $handler(new ProductSearchReindexMessage(42));
            self::fail("Expected recoverable Messenger exception.");
        } catch (RecoverableMessageHandlingException $exception) {
            self::assertSame(5000, $exception->getRetryDelay());
            self::assertTrue($exception->forceRetry());
            self::assertInstanceOf(ProductSearchRebuildInProgress::class, $exception->getPrevious());
        }
    }
}

final class TestProductSearchIncrementalIndexer implements ProductSearchIncrementalIndexInterface
{
    /** @var int[] */
    public array $catalogElementIds = [];

    public function __construct(
        private readonly bool $rebuildInProgress = false,
    ) {
    }

    public function reindex(int $catalogElementId): void
    {
        $this->catalogElementIds[] = $catalogElementId;

        if ($this->rebuildInProgress) {
            throw new ProductSearchRebuildInProgress();
        }
    }
}
