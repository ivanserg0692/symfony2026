<?php

namespace App\Tests\Unit\Grpc;

use App\Grpc\CatalogInventoryClient;
use App\Grpc\InsufficientStockException;
use App\Grpc\InventoryItemNotFoundException;
use App\Grpc\InventoryServiceUnavailableException;
use Grpc\Catalog\V1\DeductStocksRequest;
use Grpc\Catalog\V1\DeductStocksResponse;
use Grpc\Catalog\V1\InventoryServiceClient;
use Grpc\Catalog\V1\ProductDeduction;
use Grpc\Catalog\V1\StoreDeduction;
use PHPUnit\Framework\TestCase;

final class CatalogInventoryClientTest extends TestCase
{
    public function testBuildsDeductStocksRequestWithStoreId(): void
    {
        $nativeClient = $this->nativeClient(new DeductStocksResponse(["operation_id" => "op-1"]));
        $client = new CatalogInventoryClient($nativeClient);

        $client->deductStocks('op-1', [
            ["productId" => 10, "quantity" => 5, "storeId" => 3],
        ]);

        self::assertSame('op-1', $nativeClient->lastDeductStocksRequest->getOperationId());
        self::assertSame(10, (int) $nativeClient->lastDeductStocksRequest->getItems()[0]->getProductId());
        self::assertSame(5, $nativeClient->lastDeductStocksRequest->getItems()[0]->getRequestedQuantity());
        self::assertTrue($nativeClient->lastDeductStocksRequest->getItems()[0]->hasStoreId());
        self::assertSame(3, (int) $nativeClient->lastDeductStocksRequest->getItems()[0]->getStoreId());
    }

    public function testDoesNotSetStoreIdWhenItIsNotProvided(): void
    {
        $nativeClient = $this->nativeClient(new DeductStocksResponse(["operation_id" => "op-1"]));
        $client = new CatalogInventoryClient($nativeClient);

        $client->deductStocks('op-1', [
            ["productId" => 20, "quantity" => 2, "storeId" => null],
        ]);

        self::assertFalse($nativeClient->lastDeductStocksRequest->getItems()[0]->hasStoreId());
    }

    public function testConvertsSuccessfulDeductStocksResponse(): void
    {
        $nativeClient = $this->nativeClient(new DeductStocksResponse([
            "operation_id" => "op-1",
            "products" => [
                new ProductDeduction([
                    "product_id" => 10,
                    "total_deducted_quantity" => 7,
                    "stores" => [
                        new StoreDeduction(["store_id" => 1, "deducted_quantity" => 5]),
                        new StoreDeduction(["store_id" => 2, "deducted_quantity" => 2]),
                    ],
                ]),
            ],
        ]));
        $client = new CatalogInventoryClient($nativeClient);

        $result = $client->deductStocks('op-1', [["productId" => 10, "quantity" => 7]]);

        self::assertSame('op-1', $result->operationId);
        self::assertSame(10, $result->products[0]->productId);
        self::assertSame(7, $result->products[0]->totalDeductedQuantity);
        self::assertSame(1, $result->products[0]->stores[0]->storeId);
        self::assertSame(5, $result->products[0]->stores[0]->deductedQuantity);
        self::assertSame(2, $result->products[0]->stores[1]->storeId);
        self::assertSame(2, $result->products[0]->stores[1]->deductedQuantity);
    }

    public function testFailedPreconditionIsMappedToInsufficientStockException(): void
    {
        $client = new CatalogInventoryClient($this->nativeClient(null, \Grpc\STATUS_FAILED_PRECONDITION, 'insufficient stock'));

        $this->expectException(InsufficientStockException::class);

        $client->deductStocks('op-1', [["productId" => 10, "quantity" => 7]]);
    }

    public function testNotFoundIsMappedToInventoryItemNotFoundException(): void
    {
        $client = new CatalogInventoryClient($this->nativeClient(null, \Grpc\STATUS_NOT_FOUND, 'not found'));

        $this->expectException(InventoryItemNotFoundException::class);

        $client->deductStocks('op-1', [["productId" => 10, "quantity" => 7]]);
    }

    public function testUnavailableIsMappedToInventoryServiceUnavailableException(): void
    {
        $client = new CatalogInventoryClient($this->nativeClient(null, \Grpc\STATUS_UNAVAILABLE, 'unavailable'));

        $this->expectException(InventoryServiceUnavailableException::class);

        $client->deductStocks('op-1', [["productId" => 10, "quantity" => 7]]);
    }

    private function nativeClient(?DeductStocksResponse $response, int $statusCode = \Grpc\STATUS_OK, string $details = ''): InventoryServiceClient
    {
        return new class($response, $statusCode, $details) extends InventoryServiceClient {
            public ?DeductStocksRequest $lastDeductStocksRequest = null;

            public function __construct(
                private readonly ?DeductStocksResponse $response,
                private readonly int $statusCode,
                private readonly string $details,
            ) {
            }

            public function DeductStocks(\Grpc\Catalog\V1\DeductStocksRequest $argument, $metadata = [], $options = [])
            {
                $this->lastDeductStocksRequest = $argument;

                return new class($this->response, $this->statusCode, $this->details) {
                    public function __construct(
                        private readonly ?DeductStocksResponse $response,
                        private readonly int $statusCode,
                        private readonly string $details,
                    ) {
                    }

                    public function wait(): array
                    {
                        return [
                            $this->response,
                            (object) [
                                "code" => $this->statusCode,
                                "details" => $this->details,
                            ],
                        ];
                    }
                };
            }
        };
    }
}
