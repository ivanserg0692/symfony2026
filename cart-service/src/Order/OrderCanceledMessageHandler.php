<?php

namespace App\Order;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OrderCanceledMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(OrderCanceledMessage $message): void
    {
        // TODO: restore deducted stocks through Inventory gRPC.
        $this->logger->info("Order was canceled.", [
            "orderId" => $message->orderId,
            "ownerId" => $message->ownerId,
            "operationId" => $message->operationId,
            "canceledAt" => $message->canceledAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
