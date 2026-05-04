<?php

namespace App\MessengerBatch;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

final readonly class MessengerBatchMessageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessengerBatchManager $batchManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onMessageHandled',
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof MessengerBatchMessageInterface) {
            return;
        }

        $this->batchManager->markJobProcessed($message->getBatchId());
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof MessengerBatchMessageInterface) {
            return;
        }

        $this->batchManager->markJobFailed($message->getBatchId());
    }
}
