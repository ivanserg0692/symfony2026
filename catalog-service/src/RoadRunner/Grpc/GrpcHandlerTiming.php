<?php

namespace App\RoadRunner\Grpc;

use Psr\Log\LoggerInterface;

final class GrpcHandlerTiming
{
    private static int $sequence = 0;

    private ?int $startedAt = null;
    private ?float $startedAtUnixMs = null;
    private ?int $lastMarkAt = null;
    private ?string $callId = null;
    private ?string $method = null;
    private array $stages = [];

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function start(string $method): void
    {
        $this->startedAt = null;
        $this->startedAtUnixMs = null;
        $this->lastMarkAt = null;
        $this->callId = null;
        $this->method = null;
        $this->stages = [];

        if (!in_array($method, ['GetProductPrices', 'DeductStocks'], true)) {
            return;
        }

        $this->startedAt = $this->lastMarkAt = hrtime(true);
        $this->startedAtUnixMs = microtime(true) * 1000;
        $this->callId = getmypid().'-'.++self::$sequence;
        $this->method = $method;
    }

    public function mark(string $stage): void
    {
        if ($this->startedAt === null || $this->lastMarkAt === null) {
            return;
        }

        $now = hrtime(true);
        $this->stages[] = [
            'stage' => $stage,
            'since_previous_ms' => round(($now - $this->lastMarkAt) / 1_000_000, 2),
            'since_start_ms' => round(($now - $this->startedAt) / 1_000_000, 2),
        ];
        $this->lastMarkAt = $now;
    }

    public function finish(?\Throwable $exception = null): void
    {
        if ($this->startedAt === null) {
            return;
        }

        $this->mark('invoker.finished');
        $context = [
            'call_id' => $this->callId,
            'method' => $this->method,
            'started_at_unix_ms' => $this->startedAtUnixMs,
            'duration_ms' => $this->stages[array_key_last($this->stages)]['since_start_ms'],
            'stages' => $this->stages,
        ];

        if ($exception !== null) {
            $context['exception_class'] = $exception::class;
        }

        $this->startedAt = null;
        $this->startedAtUnixMs = null;
        $this->lastMarkAt = null;
        $this->callId = null;
        $this->method = null;
        $this->stages = [];

        $this->logger->debug('gRPC handler timing.', $context);
    }
}
