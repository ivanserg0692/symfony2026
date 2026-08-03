<?php

namespace App\Grpc;

use Grpc\Catalog\V1\InventoryServiceClient;
use Grpc\ChannelCredentials;

final class CatalogInventoryServiceClientFactory
{
    public function __construct(private readonly string $catalogGrpcDsn)
    {
    }

    public function create(): InventoryServiceClient
    {
        return new InventoryServiceClient($this->catalogGrpcDsn, [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }
}
