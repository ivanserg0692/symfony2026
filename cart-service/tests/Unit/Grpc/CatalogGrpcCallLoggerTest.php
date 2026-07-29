<?php

namespace App\Tests\Unit\Grpc;

use App\Grpc\CatalogGrpcCallLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class CatalogGrpcCallLoggerTest extends TestCase
{
    public function testLogsCompletedCallWithDurationAndStatus(): void
    {
        $logger = new RecordingLogger();
        $callLogger = new CatalogGrpcCallLogger($logger);

        $result = $callLogger->wait('GetProductSnapshots', new class {
            public function wait(): array
            {
                return [null, (object) ['code' => \Grpc\STATUS_OK, 'details' => '']];
            }
        }, ['snapshot_count' => 2]);

        self::assertCount(2, $result);
        self::assertSame('debug', $logger->records[0]['level']);
        self::assertSame('Catalog gRPC call completed.', $logger->records[0]['message']);
        self::assertSame('GetProductSnapshots', $logger->records[0]['context']['method']);
        self::assertSame(\Grpc\STATUS_OK, $logger->records[0]['context']['status_code']);
        self::assertSame(2, $logger->records[0]['context']['snapshot_count']);
        self::assertArrayHasKey('duration_ms', $logger->records[0]['context']);
    }

    public function testLogsFailedCallAndRethrowsException(): void
    {
        $logger = new RecordingLogger();
        $callLogger = new CatalogGrpcCallLogger($logger);
        $exception = new \RuntimeException('network failed');

        $this->expectExceptionObject($exception);

        try {
            $callLogger->wait('DeductStocks', new class($exception) {
                public function __construct(private readonly \RuntimeException $exception)
                {
                }

                public function wait(): array
                {
                    throw $this->exception;
                }
            }, ['item_count' => 3]);
        } finally {
            self::assertSame('warning', $logger->records[0]['level']);
            self::assertSame('Catalog gRPC call failed.', $logger->records[0]['message']);
            self::assertSame('DeductStocks', $logger->records[0]['context']['method']);
            self::assertSame(\RuntimeException::class, $logger->records[0]['context']['exception_class']);
            self::assertSame('network failed', $logger->records[0]['context']['exception_message']);
            self::assertSame(3, $logger->records[0]['context']['item_count']);
            self::assertArrayHasKey('duration_ms', $logger->records[0]['context']);
        }
    }
}

final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|\Stringable, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
