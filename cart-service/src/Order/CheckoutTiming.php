<?php

namespace App\Order;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CheckoutTiming
{
    private ?int $startedAtNs = null;
    private ?int $previousStageAtNs = null;
    private ?float $startedAtUnixMs = null;
    private ?string $exceptionClass = null;
    private ?string $failedAfterStage = null;
    private array $stages = [];

    public function __construct(
        private readonly bool $appTracingEnabled,
        #[Autowire(service: 'app.checkout_timing_logger')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function start(): void
    {
        if (!$this->appTracingEnabled) {
            return;
        }

        $this->startedAtNs = hrtime(true);
        $this->previousStageAtNs = $this->startedAtNs;
        $this->startedAtUnixMs = round(microtime(true) * 1000, 3);
        $this->exceptionClass = null;
        $this->failedAfterStage = null;
        $this->stages = [];
        $this->mark('controller.entered');
    }

    public function mark(string $stage): void
    {
        if ($this->startedAtNs === null) {
            return;
        }

        $now = hrtime(true);
        $this->stages[] = [
            'stage' => $stage,
            'since_previous_ms' => round(($now - $this->previousStageAtNs) / 1_000_000, 2),
            'since_start_ms' => round(($now - $this->startedAtNs) / 1_000_000, 2),
        ];
        $this->previousStageAtNs = $now;
    }

    public function fail(\Throwable $exception): void
    {
        if ($this->startedAtNs !== null && $this->exceptionClass === null) {
            $this->exceptionClass = $exception::class;
            $this->failedAfterStage = $this->stages[array_key_last($this->stages)]['stage'] ?? null;
        }
    }

    public function finish(): void
    {
        if ($this->startedAtNs === null) {
            return;
        }

        $context = [
            'started_at_unix_ms' => $this->startedAtUnixMs,
            'duration_ms' => round((hrtime(true) - $this->startedAtNs) / 1_000_000, 2),
            'outcome' => $this->exceptionClass === null ? 'completed' : 'failed',
            'exception_class' => $this->exceptionClass,
            'failed_after_stage' => $this->failedAfterStage,
            'last_stage' => $this->stages[array_key_last($this->stages)]['stage'] ?? null,
            'stages' => $this->stages,
        ];

        $this->startedAtNs = null;

        try {
            $this->logger->info('Checkout timing. '.json_encode($context, JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            // Timing must never affect checkout or its response.
        }
    }
}
