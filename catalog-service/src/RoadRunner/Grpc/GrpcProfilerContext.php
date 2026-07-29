<?php

namespace App\RoadRunner\Grpc;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final class GrpcProfilerContext
{
    private ?GrpcDataCollector $dataCollector = null;
    private ?Request $request = null;
    private mixed $responseFactory = null;
    private ?\Throwable $exception = null;
    private array $logContext = [];
    private bool $requestPushed = false;

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function setDataCollector(?GrpcDataCollector $dataCollector): void
    {
        $this->dataCollector = $dataCollector;
    }

    public function activate(Request $request): void
    {
        $this->requestStack->push($request);
        $this->request = $request;
        $this->requestPushed = true;
    }

    public function schedule(Request $request, callable $responseFactory, ?\Throwable $exception, array $logContext): void
    {
        $this->request = $request;
        $this->responseFactory = $responseFactory;
        $this->exception = $exception;
        $this->logContext = $logContext;
    }

    public function flush(Profiler $profiler, ?LoggerInterface $logger = null): void
    {
        if ($this->request === null || $this->responseFactory === null) {
            $this->clear();

            return;
        }

        $startedAt = microtime(true);

        try {
            $response = ($this->responseFactory)();
            $this->dataCollector?->setCallData($this->createCollectorData());
            $profile = $profiler->collect($this->request, $response, $this->exception);

            if ($profile === null) {
                $logger?->debug('gRPC profiler skipped.', $this->logContext + [
                    'post_response_profiling_duration_ms' => $this->durationMs($startedAt),
                ]);

                return;
            }

            $profiler->saveProfile($profile);

            $logger?->debug('gRPC profiler saved.', $this->logContext + [
                'post_response_profiling_duration_ms' => $this->durationMs($startedAt),
                'profiler_token' => $profile->getToken(),
            ]);
        } catch (\Throwable $exception) {
            $logger?->warning('gRPC profiler save failed.', $this->logContext + [
                'post_response_profiling_duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        } finally {
            $this->clear();
        }
    }

    private function clear(): void
    {
        if ($this->requestPushed) {
            $this->requestStack->pop();
        }

        $this->request = null;
        $this->responseFactory = null;
        $this->exception = null;
        $this->logContext = [];
        $this->requestPushed = false;
    }

    /**
     * @return array<string, mixed>
     */
    private function createCollectorData(): array
    {
        if ($this->request === null) {
            return [];
        }

        return [
            'service' => $this->request->attributes->get('_grpc_service'),
            'method' => $this->request->attributes->get('_grpc_method'),
            'request_type' => $this->request->attributes->get('_grpc_request_type'),
            'response_type' => $this->request->attributes->get('_grpc_response_type'),
            'handler_duration_ms' => $this->logContext['handler_duration_ms'] ?? null,
            'total_duration_ms' => $this->logContext['total_duration_ms'] ?? null,
            'exception_class' => $this->exception !== null ? $this->exception::class : null,
            'exception_message' => $this->exception?->getMessage(),
        ];
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
