<?php

namespace App\Grpc;

use Grpc\Catalog\V1\CheckStockRequest;
use Grpc\Catalog\V1\DeductStockItem;
use Grpc\Catalog\V1\DeductStocksRequest;
use Grpc\Catalog\V1\GetProductPricesRequest;
use Grpc\Catalog\V1\InventoryServiceClient;

class CatalogInventoryClient
{
    public function __construct(private readonly InventoryServiceClient $client)
    {
    }

    public function checkStock(int $productId, int $requestedQuantity): CatalogStockResponse
    {
        $request = new CheckStockRequest();
        $request->setProductId($productId);
        $request->setRequestedQuantity($requestedQuantity);

        [$response, $status] = $this->client->CheckStock($request)->wait();

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

    /**
     * @param int[] $productIds
     *
     * @return list<CatalogProductPrice>
     */
    public function getProductPrices(array $productIds): array
    {
        $request = new GetProductPricesRequest();
        $request->setProductIds($productIds);

        [$response, $status] = $this->client->GetProductPrices($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            $this->throwGetProductPricesException($status->code, $status->details ?? '');
        }

        if ($response === null) {
            throw new InventoryCommunicationException('Catalog gRPC response is empty.');
        }

        $prices = [];
        foreach ($response->getPrices() as $price) {
            $prices[] = new CatalogProductPrice(
                (int) $price->getProductId(),
                $price->getUnitPrice(),
                $price->getUnitDiscount(),
                $price->getFinalUnitPrice(),
            );
        }

        return $prices;
    }

    /**
     * @param list<array{productId: int, quantity: int, storeId?: int|null}> $items
     */
    public function deductStocks(string $operationId, array $items): CatalogDeductStocksResult
    {
        $request = new DeductStocksRequest();
        $request->setOperationId($operationId);

        foreach ($items as $item) {
            $requestItem = new DeductStockItem();
            $requestItem->setProductId($item["productId"]);
            $requestItem->setRequestedQuantity($item["quantity"]);

            if (array_key_exists("storeId", $item) && $item["storeId"] !== null) {
                $requestItem->setStoreId($item["storeId"]);
            }

            $request->getItems()[] = $requestItem;
        }

        [$response, $status] = $this->client->DeductStocks($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            $this->throwDeductStocksException($status->code, $status->details ?? '');
        }

        if ($response === null) {
            throw new InventoryCommunicationException('Catalog gRPC response is empty.');
        }

        $products = [];
        foreach ($response->getProducts() as $product) {
            $stores = [];

            foreach ($product->getStores() as $store) {
                $stores[] = new CatalogStoreDeduction(
                    (int) $store->getStoreId(),
                    $store->getDeductedQuantity(),
                );
            }

            $products[] = new CatalogProductDeduction(
                (int) $product->getProductId(),
                $product->getTotalDeductedQuantity(),
                (int) $product->getProductSnapshotId(),
                $stores,
            );
        }

        return new CatalogDeductStocksResult($response->getOperationId(), $products);
    }

    private function throwDeductStocksException(int $statusCode, string $details): never
    {
        $message = $details !== '' ? $details : 'Catalog gRPC request failed.';

        throw match ($statusCode) {
            \Grpc\STATUS_INVALID_ARGUMENT => new InvalidInventoryRequestException($message),
            \Grpc\STATUS_NOT_FOUND => new InventoryItemNotFoundException($message),
            \Grpc\STATUS_FAILED_PRECONDITION => new InsufficientStockException($message),
            \Grpc\STATUS_UNAVAILABLE, \Grpc\STATUS_DEADLINE_EXCEEDED => new InventoryServiceUnavailableException($message),
            default => new InventoryCommunicationException($message),
        };
    }

    private function throwGetProductPricesException(int $statusCode, string $details): never
    {
        $message = $details !== '' ? $details : 'Catalog gRPC request failed.';

        throw match ($statusCode) {
            \Grpc\STATUS_INVALID_ARGUMENT => new InvalidInventoryRequestException($message),
            \Grpc\STATUS_NOT_FOUND => new InventoryItemNotFoundException($message),
            \Grpc\STATUS_FAILED_PRECONDITION => new ProductPriceUnavailableException($message),
            \Grpc\STATUS_UNAVAILABLE, \Grpc\STATUS_DEADLINE_EXCEEDED => new InventoryServiceUnavailableException($message),
            default => new InventoryCommunicationException($message),
        };
    }
}
