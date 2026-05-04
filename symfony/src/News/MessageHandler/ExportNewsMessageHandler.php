<?php

namespace App\News\MessageHandler;

use App\MessengerBatch\MessengerBatchManager;
use App\MessengerBatch\MessengerBatchFinalizableMessageInterface;
use App\News\Message\ExportNewsMessage;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExportNewsMessageHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly MessengerBatchManager $batchManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ExportNewsMessage $message, ?Acknowledger $ack = null): mixed
    {
        if (null === $ack) {
            $this->handleSynchronously($message);

            return null;
        }

        return $this->handle($message, $ack);
    }

    private function process(array $jobs): void
    {
        foreach ($jobs as [$message, $ack]) {
            \assert($message instanceof ExportNewsMessage);

            try {
                $this->exportNews($message);
                $this->finalizeBatchIfComplete($message);
                $ack->ack();
            } catch (\Throwable $exception) {
                $ack->nack($exception);
            }
        }
    }

    private function getBatchSize(): int
    {
        return self::BATCH_SIZE;
    }

    private function handleSynchronously(ExportNewsMessage $message): void
    {
        $this->exportNews($message);
        $this->finalizeBatchIfComplete($message);
    }

    private function exportNews(ExportNewsMessage $message): void
    {
        throw new \LogicException('News export implementation is not configured.');
    }

    private function finalizeBatchIfComplete(ExportNewsMessage $message): void
    {
        if (!$this->batchManager->markJobProcessed($message->getBatchId())) {
            return;
        }

        if ($message instanceof MessengerBatchFinalizableMessageInterface) {
            $this->messageBus->dispatch($message->createFinalizeMessage());
        }
    }
}
