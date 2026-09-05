<?php

namespace App\Search\Product\Infrastructure\Messenger;

use App\Search\Product\Application\ProductSearchRebuildInProgress;
use App\Search\Product\Port\Input\ProductSearchIncrementalIndexInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler(fromTransport: 'catalog_search')]
final readonly class ProductSearchReindexMessageHandler
{
    private const int REINDEX_LOCK_RETRY_DELAY_MS = 5000;

    public function __construct(
        private ProductSearchIncrementalIndexInterface $incrementalIndexer,
    ) {
    }

    public function __invoke(ProductSearchReindexMessage $message): void
    {
        try {
            $this->incrementalIndexer->reindex($message->catalogElementId);
        } catch (ProductSearchRebuildInProgress $exception) {
            // Orchestration normally pauses the worker. This recoverable retry is a
            // safety net for races, manual worker starts, or orchestration failures.
            throw new RecoverableMessageHandlingException(
                $exception->getMessage(),
                previous: $exception,
                retryDelay: self::REINDEX_LOCK_RETRY_DELAY_MS,
            );
        }
    }
}
