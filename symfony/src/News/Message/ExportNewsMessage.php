<?php

namespace App\News\Message;

use App\MessengerBatch\MessengerBatchHandledManuallyInterface;
use App\MessengerBatch\MessengerBatchFinalizableMessageInterface;

final readonly class ExportNewsMessage implements MessengerBatchHandledManuallyInterface, MessengerBatchFinalizableMessageInterface
{
    public function __construct(
        public int $batchId,
        public int $newsId,
    ) {
    }

    public function getBatchId(): int
    {
        return $this->batchId;
    }

    public function createFinalizeMessage(): FinalizeNewsExportMessage
    {
        return new FinalizeNewsExportMessage($this->batchId);
    }
}
