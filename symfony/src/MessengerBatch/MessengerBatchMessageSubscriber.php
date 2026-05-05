<?php

namespace App\MessengerBatch;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerBatchMessageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessengerBatchManager $batchManager,
        private MessageBusInterface $messageBus,
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

        if ($message instanceof MessengerBatchHandledManuallyInterface) {
            return;
        }

        if (!$this->batchManager->markJobProcessed($message->getBatchId())) {
            return;
        }

        if ($message instanceof MessengerBatchFinalizableMessageInterface) {
            $this->messageBus->dispatch($message->createFinalizeMessage());
        }
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

        if (!$this->batchManager->markJobFailed($message->getBatchId())) {
            return;
        }

        if ($message instanceof MessengerBatchFinalizableMessageInterface) {
            $this->messageBus->dispatch($message->createFinalizeMessage());
        }
    }
}
