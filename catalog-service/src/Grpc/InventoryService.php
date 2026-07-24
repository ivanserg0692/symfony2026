<?php

namespace App\Grpc;

use App\Entity\CatalogElements;
use App\Inventory\ProductStockDeduction;
use App\Inventory\StockDeductionRequestItem;
use App\Inventory\StoreStockDeduction;
use App\Inventory\StockDeductionResult;
use App\Inventory\InsufficientInventoryStockException;
use App\Inventory\InvalidInventoryDeductionRequestException;
use App\Inventory\InventoryDeductionNotFoundException;
use App\Inventory\InventoryDeductionService;
use App\Inventory\InventoryOperationConflictException;
use App\Repository\CatalogElementsRepository;
use Grpc\Catalog\V1\CheckStockRequest;
use Grpc\Catalog\V1\CheckStockResponse;
use Grpc\Catalog\V1\DeductStocksRequest;
use Grpc\Catalog\V1\DeductStocksResponse;
use Grpc\Catalog\V1\InventoryServiceInterface;
use Grpc\Catalog\V1\ProductDeduction;
use Grpc\Catalog\V1\StoreDeduction;
use Grpc\Catalog\V1\StoreStock;
use Spiral\RoadRunner\GRPC;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\StatusCode;

final readonly class InventoryService implements InventoryServiceInterface
{
    public function __construct(
        private CatalogElementsRepository $catalogElementsRepository,
        private InventoryDeductionService $deductionService,
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
}
