<?php

namespace App\Tests\Unit\Search\Product\Application\Message;

use App\Search\Product\Application\Message\ProductSearchOutboxEvent;
use App\Search\Product\Application\Message\ProductSearchOutboxEventHandler;
use App\Search\Product\Application\Message\ProductSearchReindexMessage;
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
