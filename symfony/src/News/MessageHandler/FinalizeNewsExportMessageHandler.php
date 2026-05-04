<?php

namespace App\News\MessageHandler;

use App\News\Message\FinalizeNewsExportMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FinalizeNewsExportMessageHandler
{
    public function __invoke(FinalizeNewsExportMessage $message): void
    {
        throw new \LogicException('News export finalization implementation is not configured.');
    }
}
