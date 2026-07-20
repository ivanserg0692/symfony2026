<?php

namespace App\RoadRunner\Grpc;

use Grpc\Catalog\V1\InventoryServiceInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\Worker;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Runtime\RunnerInterface;

#[Autoconfigure(public: true)]
final readonly class RoadRunnerGrpcRunner implements RunnerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
        private ServicesResetterInterface $servicesResetter,
        private KernelInterface $kernel,
        private ?Profiler $profiler = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function run(): int
    {
        $server = new Server($this->createInvoker(), [
            "debug" => $this->kernel->isDebug(),
        ]);
        $server->registerService(InventoryServiceInterface::class, $this->inventoryService);
        $server->serve(Worker::create(), $this->finalizeRequest(...));

        return 0;
    }

    private function createInvoker(): InvokerInterface
    {
        $invoker = new Invoker();

        if (!$this->kernel->isDebug() || $this->profiler === null) {
            return $invoker;
        }

        return new ProfilingInvoker($invoker, $this->profiler, $this->logger);
    }

    private function finalizeRequest(?\Throwable $error = null): void
    {
        $this->servicesResetter->reset();
    }
}
