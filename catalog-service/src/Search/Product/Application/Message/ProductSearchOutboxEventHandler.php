<?php

namespace App\Search\Product\Application\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Relays a committed PostgreSQL outbox message to the durable RabbitMQ queue.
 */
#[AsMessageHandler(fromTransport: 'catalog_search_outbox')]
final readonly class ProductSearchOutboxEventHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ProductSearchOutboxEvent $event): void
    {
        $this->messageBus->dispatch(new ProductSearchReindexMessage($event->catalogElementId));
    }
}
