<?php

namespace App\Grpc;

use App\Entity\CatalogElements;
use App\Entity\ProductSnapshot;
use App\Inventory\ProductStockDeduction;
use App\Inventory\StockDeductionRequestItem;
use App\Inventory\StoreStockDeduction;
use App\Inventory\StockDeductionResult;
use App\Inventory\InsufficientInventoryStockException;
use App\Inventory\InvalidInventoryDeductionRequestException;
use App\Inventory\InventoryDeductionNotFoundException;
use App\Inventory\InventoryDeductionService;
use App\Inventory\InventoryOperationConflictException;
use App\Pricing\CheckoutProductNotFoundException;
use App\Pricing\CheckoutProductPrice;
use App\Pricing\CheckoutProductPriceProvider;
use App\Pricing\CheckoutProductPriceUnavailableException;
use App\Repository\CatalogElementsRepository;
use App\Repository\ProductSnapshotRepository;
use Grpc\Catalog\V1\CheckStockRequest;
use Grpc\Catalog\V1\CheckStockResponse;
use Grpc\Catalog\V1\DeductStocksRequest;
use Grpc\Catalog\V1\DeductStocksResponse;
use Grpc\Catalog\V1\GetProductPricesRequest;
use Grpc\Catalog\V1\GetProductPricesResponse;
use Grpc\Catalog\V1\GetProductSnapshotsRequest;
use Grpc\Catalog\V1\GetProductSnapshotsResponse;
use Grpc\Catalog\V1\InventoryServiceInterface;
use Grpc\Catalog\V1\ProductDeduction;
use Grpc\Catalog\V1\ProductPrice as GrpcProductPrice;
use Grpc\Catalog\V1\ProductSnapshot as GrpcProductSnapshot;
use Grpc\Catalog\V1\SnapshotProduct;
use Grpc\Catalog\V1\StoreDeduction;
use Grpc\Catalog\V1\StoreStock;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\StatusCode;

final readonly class InventoryService implements InventoryServiceInterface
{
    private const MAX_PRODUCT_SNAPSHOT_IDS = 100;

    public function __construct(
        private CatalogElementsRepository $catalogElementsRepository,
        private InventoryDeductionService $deductionService,
        private CheckoutProductPriceProvider $checkoutProductPriceProvider,
        private ProductSnapshotRepository $productSnapshotRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function CheckStock(GRPC\ContextInterface $ctx, CheckStockRequest $in): CheckStockResponse
    {
        $productId = (int) $in->getProductId();
        $element = $this->catalogElementsRepository->findOneForInventoryCheck($productId);

        if ($element === null) {
            return $this->createResponse($productId, 0, $in->getRequestedQuantity(), []);
        }

        return $this->createResponse(
            $productId,
            $element->getTotalStock(),
            $in->getRequestedQuantity(),
            $this->createStoreStocks($element),
        );
    }

    public function DeductStocks(GRPC\ContextInterface $ctx, DeductStocksRequest $in): DeductStocksResponse
    {
        try {
            return $this->mapToDeductStocksResponse($this->deductionService->deduct(
                $in->getOperationId(),
                $this->mapToStockDeductionRequestItems($in),
            ));
        } catch (InvalidInventoryDeductionRequestException $exception) {
            throw new GRPCException($exception->getMessage(), StatusCode::INVALID_ARGUMENT);
        } catch (InventoryDeductionNotFoundException $exception) {
            throw new GRPCException($exception->getMessage(), StatusCode::NOT_FOUND);
        } catch (InsufficientInventoryStockException $exception) {
            throw new GRPCException($exception->getMessage(), StatusCode::FAILED_PRECONDITION);
        } catch (InventoryOperationConflictException $exception) {
            throw new GRPCException($exception->getMessage(), StatusCode::ALREADY_EXISTS);
        } catch (\Throwable) {
            throw new GRPCException("Internal inventory error.", StatusCode::INTERNAL);
        }
    }

    public function GetProductPrices(GRPC\ContextInterface $ctx, GetProductPricesRequest $in): GetProductPricesResponse
    {
        try {
            return new GetProductPricesResponse([
                "prices" => $this->fetchGrpcProductPrices($this->normalizeProductIds($in)),
            ]);
        } catch (GRPCException $exception) {
            throw $exception;
        } catch (CheckoutProductNotFoundException $exception) {
            throw new GRPCException($exception->getMessage(), StatusCode::NOT_FOUND);
        } catch (CheckoutProductPriceUnavailableException $exception) {
            throw new GRPCException($exception->getMessage(), StatusCode::FAILED_PRECONDITION);
        } catch (\Throwable) {
            throw new GRPCException("Internal inventory error.", StatusCode::INTERNAL);
        }
    }

    public function GetProductSnapshots(GRPC\ContextInterface $ctx, GetProductSnapshotsRequest $in): GetProductSnapshotsResponse
    {
        try {
            $this->logger->debug('got a GetProductSnapshots request');
            $snapshotIds = $this->normalizeProductSnapshotIds($in);
            $snapshots = $this->productSnapshotRepository->findListByIdsForGrpc($snapshotIds);

            if (count($snapshots) !== count($snapshotIds)) {
                throw new GRPCException("Product snapshot was not found.", StatusCode::NOT_FOUND);
            }

            return new GetProductSnapshotsResponse([
                "snapshots" => array_map(
                    fn (ProductSnapshot $snapshot): GrpcProductSnapshot => $this->mapToGrpcProductSnapshot($snapshot),
                    $snapshots,
                ),
            ]);
        } catch (GRPCException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new GRPCException("Internal inventory error.", StatusCode::INTERNAL);
        }
    }

    /**
     * @param list<StoreStock> $stores
     */
    private function createResponse(
        int $productId,
        int $totalAvailableQuantity,
        int $requestedQuantity,
        array $stores,
    ): CheckStockResponse {
        return new CheckStockResponse([
            "product_id" => $productId,
            "total_available_quantity" => $totalAvailableQuantity,
            "available" => $requestedQuantity <= $totalAvailableQuantity,
            "stores" => $stores,
        ]);
    }

    /**
     * @return list<StoreStock>
     */
    private function createStoreStocks(CatalogElements $element): array
    {
        $stores = [];

        foreach ($element->getStoreStocks() as $storeStock) {
            $store = $storeStock->getStore();

            if ($store?->getId() === null) {
                continue;
            }

            $stores[] = new StoreStock([
                "store_id" => $store->getId(),
                "available_quantity" => $storeStock->getStock() ?? 0,
            ]);
        }

        return $stores;
    }

    /**
     * @return list<StockDeductionRequestItem>
     */
    private function mapToStockDeductionRequestItems(DeductStocksRequest $request): array
    {
        $items = [];

        foreach ($request->getItems() as $item) {
            $items[] = new StockDeductionRequestItem(
                (int) $item->getProductId(),
                $item->getRequestedQuantity(),
                $item->hasStoreId() ? (int) $item->getStoreId() : null,
            );
        }

        return $items;
    }

    private function mapToDeductStocksResponse(StockDeductionResult $result): DeductStocksResponse
    {
        return new DeductStocksResponse([
            "operation_id" => $result->operationId,
            "products" => array_map(
                fn (ProductStockDeduction $product): ProductDeduction => $this->mapToProductDeduction($product),
                $result->products,
            ),
        ]);
    }

    private function mapToProductDeduction(ProductStockDeduction $product): ProductDeduction
    {
        return new ProductDeduction([
            "product_id" => $product->productId,
            "total_deducted_quantity" => $product->totalDeductedQuantity,
            "product_snapshot_id" => $product->productSnapshotId,
            "stores" => array_map(
                fn (StoreStockDeduction $store): StoreDeduction => $this->mapToStoreDeduction($store),
                $product->stores,
            ),
        ]);
    }

    private function mapToStoreDeduction(StoreStockDeduction $store): StoreDeduction
    {
        return new StoreDeduction([
            "store_id" => $store->storeId,
            "deducted_quantity" => $store->deductedQuantity,
        ]);
    }

    /**
     * @return int[]
     */
    private function normalizeProductIds(GetProductPricesRequest $request): array
    {
        $productIds = array_map("intval", iterator_to_array($request->getProductIds()));

        if ($productIds === []) {
            throw new GRPCException("Product ids list must not be empty.", StatusCode::INVALID_ARGUMENT);
        }

        foreach ($productIds as $productId) {
            if ($productId <= 0) {
                throw new GRPCException("Product id must be a positive integer.", StatusCode::INVALID_ARGUMENT);
            }
        }

        if (count($productIds) !== count(array_unique($productIds))) {
            throw new GRPCException("Product ids list must not contain duplicates.", StatusCode::INVALID_ARGUMENT);
        }

        return $productIds;
    }

    /**
     * @return int[]
     */
    private function normalizeProductSnapshotIds(GetProductSnapshotsRequest $request): array
    {
        $snapshotIds = array_map("intval", iterator_to_array($request->getProductSnapshotIds()));

        if ($snapshotIds === []) {
            throw new GRPCException("Product snapshot ids list must not be empty.", StatusCode::INVALID_ARGUMENT);
        }

        if (count($snapshotIds) > self::MAX_PRODUCT_SNAPSHOT_IDS) {
            throw new GRPCException("Product snapshot ids list is too large.", StatusCode::INVALID_ARGUMENT);
        }

        foreach ($snapshotIds as $snapshotId) {
            if ($snapshotId <= 0) {
                throw new GRPCException("Product snapshot id must be a positive integer.", StatusCode::INVALID_ARGUMENT);
            }
        }

        if (count($snapshotIds) !== count(array_unique($snapshotIds))) {
            throw new GRPCException("Product snapshot ids list must not contain duplicates.", StatusCode::INVALID_ARGUMENT);
        }

        return $snapshotIds;
    }

    private function mapToGrpcProductSnapshot(ProductSnapshot $snapshot): GrpcProductSnapshot
    {
        $product = $snapshot->getProduct();
        $snapshotId = $snapshot->getId();
        $originalProductId = $snapshot->getOriginalProductId();

        if ($product === null || $snapshotId === null || $originalProductId === null || $product->getId() === null) {
            throw new GRPCException("Product snapshot data is incomplete.", StatusCode::INTERNAL);
        }

        return new GrpcProductSnapshot([
            "id" => $snapshotId,
            "original_product_id" => $originalProductId,
            "product" => new SnapshotProduct([
                "id" => $product->getId(),
                "name" => $product->getName() ?? "",
                "created_at" => ($product->getCreatedAt() ?? new \DateTimeImmutable("@0"))->format(\DateTimeInterface::ATOM),
                "active" => $product->isActive() ?? false,
                "created_by" => $product->getCreatedBy() ?? 0,
                "description" => $product->getDescription() ?? "",
                "slug" => $product->getSlug() ?? "",
                "picture_id" => $product->getPictureId() ?? "",
            ]),
        ]);
    }

    /**
     * @param int[] $productIds
     *
     * @return list<GrpcProductPrice>
     */
    private function fetchGrpcProductPrices(array $productIds): array
    {
        return array_map(
            fn (CheckoutProductPrice $price): GrpcProductPrice => $this->mapToGrpcProductPrice($price),
            array_values($this->checkoutProductPriceProvider->getPricesForProducts($productIds)),
        );
    }

    private function mapToGrpcProductPrice(CheckoutProductPrice $price): GrpcProductPrice
    {
        return new GrpcProductPrice([
            "product_id" => $price->productId,
            "unit_price_minor_units" => $price->unitPriceMinorUnits,
            "unit_discount_minor_units" => $price->unitDiscountMinorUnits,
            "final_unit_price_minor_units" => $price->finalUnitPriceMinorUnits,
        ]);
    }
}
