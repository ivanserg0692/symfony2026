<?php

namespace App\Tests\Unit\Grpc;

use App\Grpc\CatalogInventoryClient;
use App\Grpc\InsufficientStockException;
use App\Grpc\InventoryItemNotFoundException;
use App\Grpc\InventoryServiceUnavailableException;
use App\Grpc\ProductPriceUnavailableException;
use Grpc\Catalog\V1\DeductStocksRequest;
use Grpc\Catalog\V1\DeductStocksResponse;
use Grpc\Catalog\V1\GetProductPricesRequest;
use Grpc\Catalog\V1\GetProductPricesResponse;
use Grpc\Catalog\V1\InventoryServiceClient;
use Grpc\Catalog\V1\ProductDeduction;
use Grpc\Catalog\V1\ProductPrice;
use Grpc\Catalog\V1\StoreDeduction;
use PHPUnit\Framework\TestCase;

final class CatalogInventoryClientTest extends TestCase
{
    public function testBuildsGetProductPricesRequest(): void
    {
        $nativeClient = $this->nativeClient(productPricesResponse: new GetProductPricesResponse());
        $client = new CatalogInventoryClient($nativeClient);

        $client->getProductPrices([10, 20]);

        self::assertSame([10, 20], iterator_to_array($nativeClient->lastGetProductPricesRequest->getProductIds()));
    }

    public function testConvertsSuccessfulGetProductPricesResponse(): void
    {
        $nativeClient = $this->nativeClient(productPricesResponse: new GetProductPricesResponse([
            "prices" => [
                new ProductPrice([
                    "product_id" => 10,
                    "unit_price_minor_units" => 1000,
                    "unit_discount_minor_units" => 150,
                    "final_unit_price_minor_units" => 850,
                ]),
            ],
        ]));
        $client = new CatalogInventoryClient($nativeClient);

        $prices = $client->getProductPrices([10]);

        self::assertSame(10, $prices[0]->productId);
        self::assertSame(1000, $prices[0]->unitPriceMinorUnits);
        self::assertSame(150, $prices[0]->unitDiscountMinorUnits);
        self::assertSame(850, $prices[0]->finalUnitPriceMinorUnits);
    }

    public function testGetProductPricesFailedPreconditionIsMappedToProductPriceUnavailableException(): void
    {
        $client = new CatalogInventoryClient($this->nativeClient(
            productPricesResponse: null,
            productPricesStatusCode: \Grpc\STATUS_FAILED_PRECONDITION,
            productPricesDetails: 'price unavailable',
        ));

        $this->expectException(ProductPriceUnavailableException::class);

        $client->getProductPrices([10]);
    }

    public function testBuildsDeductStocksRequestWithStoreId(): void
    {
        $nativeClient = $this->nativeClient(deductStocksResponse: new DeductStocksResponse(["operation_id" => "op-1"]));
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
        $nativeClient = $this->nativeClient(deductStocksResponse: new DeductStocksResponse(["operation_id" => "op-1"]));
        $client = new CatalogInventoryClient($nativeClient);

        $client->deductStocks('op-1', [
            ["productId" => 20, "quantity" => 2, "storeId" => null],
        ]);

        self::assertFalse($nativeClient->lastDeductStocksRequest->getItems()[0]->hasStoreId());
    }

    public function testConvertsSuccessfulDeductStocksResponse(): void
    {
        $nativeClient = $this->nativeClient(deductStocksResponse: new DeductStocksResponse([
            "operation_id" => "op-1",
            "products" => [
                new ProductDeduction([
                    "product_id" => 10,
                    "total_deducted_quantity" => 7,
                    "product_snapshot_id" => 501,
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
        self::assertSame(501, $result->products[0]->productSnapshotId);
        self::assertSame(1, $result->products[0]->stores[0]->storeId);
        self::assertSame(5, $result->products[0]->stores[0]->deductedQuantity);
        self::assertSame(2, $result->products[0]->stores[1]->storeId);
        self::assertSame(2, $result->products[0]->stores[1]->deductedQuantity);
    }

    public function testFailedPreconditionIsMappedToInsufficientStockException(): void
    {
        $client = new CatalogInventoryClient($this->nativeClient(
            deductStocksResponse: null,
            deductStocksStatusCode: \Grpc\STATUS_FAILED_PRECONDITION,
            deductStocksDetails: 'insufficient stock',
        ));

        $this->expectException(InsufficientStockException::class);

        $client->deductStocks('op-1', [["productId" => 10, "quantity" => 7]]);
    }

    public function testNotFoundIsMappedToInventoryItemNotFoundException(): void
    {
        $client = new CatalogInventoryClient($this->nativeClient(
            deductStocksResponse: null,
            deductStocksStatusCode: \Grpc\STATUS_NOT_FOUND,
            deductStocksDetails: 'not found',
        ));

        $this->expectException(InventoryItemNotFoundException::class);

        $client->deductStocks('op-1', [["productId" => 10, "quantity" => 7]]);
    }

    public function testUnavailableIsMappedToInventoryServiceUnavailableException(): void
    {
        $client = new CatalogInventoryClient($this->nativeClient(
            deductStocksResponse: null,
            deductStocksStatusCode: \Grpc\STATUS_UNAVAILABLE,
            deductStocksDetails: 'unavailable',
        ));

        $this->expectException(InventoryServiceUnavailableException::class);

        $client->deductStocks('op-1', [["productId" => 10, "quantity" => 7]]);
    }

    private function nativeClient(
        ?DeductStocksResponse $deductStocksResponse = null,
        int $deductStocksStatusCode = \Grpc\STATUS_OK,
        string $deductStocksDetails = '',
        ?GetProductPricesResponse $productPricesResponse = null,
        int $productPricesStatusCode = \Grpc\STATUS_OK,
        string $productPricesDetails = '',
    ): InventoryServiceClient {
        return new class(
            $deductStocksResponse,
            $deductStocksStatusCode,
            $deductStocksDetails,
            $productPricesResponse,
            $productPricesStatusCode,
            $productPricesDetails,
        ) extends InventoryServiceClient {
            public ?DeductStocksRequest $lastDeductStocksRequest = null;
            public ?GetProductPricesRequest $lastGetProductPricesRequest = null;

            public function __construct(
                private readonly ?DeductStocksResponse $deductStocksResponse,
                private readonly int $deductStocksStatusCode,
                private readonly string $deductStocksDetails,
                private readonly ?GetProductPricesResponse $productPricesResponse,
                private readonly int $productPricesStatusCode,
                private readonly string $productPricesDetails,
            ) {
            }

            public function DeductStocks(\Grpc\Catalog\V1\DeductStocksRequest $argument, $metadata = [], $options = [])
            {
                $this->lastDeductStocksRequest = $argument;

                return new class($this->deductStocksResponse, $this->deductStocksStatusCode, $this->deductStocksDetails) {
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

            public function GetProductPrices(\Grpc\Catalog\V1\GetProductPricesRequest $argument, $metadata = [], $options = [])
            {
                $this->lastGetProductPricesRequest = $argument;

                return new class($this->productPricesResponse, $this->productPricesStatusCode, $this->productPricesDetails) {
                    public function __construct(
                        private readonly ?GetProductPricesResponse $response,
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
