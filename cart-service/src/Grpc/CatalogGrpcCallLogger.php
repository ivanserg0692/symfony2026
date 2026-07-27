<?php

namespace App\Grpc;

use Psr\Log\LoggerInterface;

final class CatalogGrpcCallLogger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{0: object|null, 1: object}
     */
    public function wait(string $method, object $call, array $context = []): array
    {
        $startedAt = microtime(true);

        try {
            [$response, $status] = $call->wait();

            $this->logger->debug('Catalog gRPC call completed.', [
                'method' => $method,
                'status_code' => $status->code ?? null,
                'status_details' => $status->details ?? '',
                'duration_ms' => $this->durationMs($startedAt),
                ...$context,
            ]);

            return [$response, $status];
        } catch (\Throwable $exception) {
            $this->logger->warning('Catalog gRPC call failed.', [
                'method' => $method,
                'duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                ...$context,
            ]);

            throw $exception;
        }
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
