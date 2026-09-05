<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Messenger;

use App\Search\Product\Infrastructure\Messenger\ProductSearchOutboxEvent;
use App\Search\Product\Infrastructure\Messenger\ProductSearchOutboxEventHandler;
use App\Search\Product\Infrastructure\Messenger\ProductSearchReindexMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProductSearchOutboxEventHandlerTest extends TestCase
{
    public function testItRelaysOnlyTheCatalogElementId(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method("dispatch")
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof ProductSearchReindexMessage
                    && $message->catalogElementId === 42,
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        (new ProductSearchOutboxEventHandler($bus))(new ProductSearchOutboxEvent(42));
    }

    public function testRabbitMqDispatchFailureIsPropagatedForDoctrineTransportRetry(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method("dispatch")
            ->willThrowException(new \RuntimeException("RabbitMQ unavailable."));

        $this->expectExceptionMessage("RabbitMQ unavailable.");

        (new ProductSearchOutboxEventHandler($bus))(new ProductSearchOutboxEvent(42));
    }
}
