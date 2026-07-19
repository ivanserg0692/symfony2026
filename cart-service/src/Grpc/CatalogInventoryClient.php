<?php

namespace App\Grpc;

use Grpc\Catalog\V1\CheckStockRequest;
use Grpc\Catalog\V1\InventoryServiceClient;
use Grpc\ChannelCredentials;

final class CatalogInventoryClient
{
    private ?InventoryServiceClient $client = null;

    public function __construct(private readonly string $catalogGrpcDsn)
    {
    }

    public function checkStock(int $productId, int $requestedQuantity): CatalogStockResponse
    {
        $request = new CheckStockRequest();
        $request->setProductId($productId);
        $request->setRequestedQuantity($requestedQuantity);

        [$response, $status] = $this->getClient()->CheckStock($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException($status->details !== '' ? $status->details : 'Catalog gRPC request failed.');
        }

        if ($response === null) {
            throw new \RuntimeException('Catalog gRPC response is empty.');
        }

        $stores = [];
        foreach ($response->getStores() as $store) {
            $stores[] = new CatalogStoreStock(
                (int) $store->getStoreId(),
                $store->getAvailableQuantity(),
            );
        }

        return new CatalogStockResponse(
            (int) $response->getProductId(),
            $response->getTotalAvailableQuantity(),
            $response->getAvailable(),
            $stores,
        );
    }

    private function getClient(): InventoryServiceClient
    {
        return $this->client ??= new InventoryServiceClient($this->catalogGrpcDsn, [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }
}
