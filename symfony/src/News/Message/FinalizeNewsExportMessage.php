<?php

namespace App\News\Message;

final readonly class FinalizeNewsExportMessage
{
    public function __construct(
        public int $batchId,
    ) {
    }
}
