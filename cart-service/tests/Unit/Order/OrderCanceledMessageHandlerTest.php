<?php

namespace App\Tests\Unit\Order;

use App\Order\OrderCanceledMessage;
use App\Order\OrderCanceledMessageHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderCanceledMessageHandlerTest extends TestCase
{
    public function testLogsCanceledOrder(): void
    {
        $logger = new class implements LoggerInterface {
            /**
             * @var list<array{level: string, message: string, context: array<string, mixed>}>
             */
            public array $records = [];

            public function emergency(\Stringable|string $message, array $context = []): void
            {
                $this->log("emergency", $message, $context);
            }

            public function alert(\Stringable|string $message, array $context = []): void
            {
                $this->log("alert", $message, $context);
            }

            public function critical(\Stringable|string $message, array $context = []): void
            {
                $this->log("critical", $message, $context);
            }

            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->log("error", $message, $context);
            }

            public function warning(\Stringable|string $message, array $context = []): void
            {
                $this->log("warning", $message, $context);
            }

            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->log("notice", $message, $context);
            }

            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->log("info", $message, $context);
            }

            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->log("debug", $message, $context);
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [
                    "level" => (string) $level,
                    "message" => (string) $message,
                    "context" => $context,
                ];
            }
        };

        $handler = new OrderCanceledMessageHandler($logger);
        $canceledAt = new \DateTimeImmutable("2026-01-02 10:00:00+00:00");

        $handler(new OrderCanceledMessage(10, 123, $canceledAt, "cart-checkout-1"));

        self::assertCount(1, $logger->records);
        self::assertSame("info", $logger->records[0]["level"]);
        self::assertSame("Order was canceled.", $logger->records[0]["message"]);
        self::assertSame(10, $logger->records[0]["context"]["orderId"]);
        self::assertSame(123, $logger->records[0]["context"]["ownerId"]);
        self::assertSame("cart-checkout-1", $logger->records[0]["context"]["operationId"]);
        self::assertSame($canceledAt->format(\DateTimeInterface::ATOM), $logger->records[0]["context"]["canceledAt"]);
    }
}
