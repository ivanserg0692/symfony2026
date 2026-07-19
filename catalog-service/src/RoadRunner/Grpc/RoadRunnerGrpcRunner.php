<?php

namespace App\RoadRunner\Grpc;

use Grpc\Catalog\V1\InventoryServiceInterface;
use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\Worker;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\Runtime\RunnerInterface;

#[Autoconfigure(public: true)]
final readonly class RoadRunnerGrpcRunner implements RunnerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
        private ServicesResetterInterface $servicesResetter,
    ) {
    }

    public function run(): int
    {
        $server = new Server(new Invoker(), [
            "debug" => false,
        ]);
        $server->registerService(InventoryServiceInterface::class, $this->inventoryService);
        $server->serve(Worker::create(), $this->finalizeRequest(...));

        return 0;
    }

    private function finalizeRequest(?\Throwable $error = null): void
    {
        $this->servicesResetter->reset();
    }
}
