<?php

namespace App\RoadRunner\Grpc;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class GrpcDataCollector extends DataCollector
{
    /**
     * @param array<string, mixed> $data
     */
    public function setCallData(array $data): void
    {
        $this->data = $data;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if ($this->data === []) {
            $this->data = [
                'service' => $request->attributes->get('_grpc_service'),
                'method' => $request->attributes->get('_grpc_method'),
                'request_type' => $request->attributes->get('_grpc_request_type'),
                'response_type' => $request->attributes->get('_grpc_response_type'),
                'handler_duration_ms' => null,
                'total_duration_ms' => null,
                'exception_class' => $exception !== null ? $exception::class : null,
                'exception_message' => $exception?->getMessage(),
            ];
        }
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'app.grpc';
    }

    public function hasCall(): bool
    {
        return $this->getService() !== null && $this->getMethod() !== null;
    }

    public function getService(): ?string
    {
        return $this->getStringValue('service');
    }

    public function getMethod(): ?string
    {
        return $this->getStringValue('method');
    }

    public function getRequestType(): ?string
    {
        return $this->getStringValue('request_type');
    }

    public function getResponseType(): ?string
    {
        return $this->getStringValue('response_type');
    }

    public function getHandlerDurationMs(): ?float
    {
        return $this->getFloatValue('handler_duration_ms');
    }

    public function getTotalDurationMs(): ?float
    {
        return $this->getFloatValue('total_duration_ms');
    }

    public function getExceptionClass(): ?string
    {
        return $this->getStringValue('exception_class');
    }

    public function getExceptionMessage(): ?string
    {
        return $this->getStringValue('exception_message');
    }

    private function getStringValue(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function getFloatValue(string $key): ?float
    {
        $value = $this->data[$key] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
