<?php

namespace App\Inventory;

use App\Entity\InventoryOperation;
use App\Entity\StoresElementsStocks;
use App\Repository\CatalogElementsRepository;
use App\Repository\InventoryOperationRepository;
use App\Repository\ProductSnapshotRepository;
use App\Repository\StoresElementsStocksRepository;
use App\Repository\StoresRepository;
use App\RoadRunner\Grpc\GrpcHandlerTiming;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InventoryDeductionService
{
    private const MAX_ITEMS = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private CatalogElementsRepository $catalogElementsRepository,
        private ProductSnapshotRepository $productSnapshotRepository,
        private StoresRepository $storesRepository,
        private StoresElementsStocksRepository $stocksRepository,
        private InventoryOperationRepository $operationRepository,
        private GrpcHandlerTiming $handlerTiming,
    ) {
    }

    /**
     * @param list<StockDeductionRequestItem> $items
     */
    public function deduct(string $operationId, array $items): StockDeductionResult
    {
        $this->handlerTiming->mark('deduction.entered');
        $operationId = trim($operationId);
        $normalizedItems = $this->normalizeItems($operationId, $items);
        $requestHash = $this->createRequestHash($normalizedItems);
        $this->handlerTiming->mark('deduction.request_normalized_and_hashed');

        $result = $this->connection->transactional(function () use ($operationId, $normalizedItems, $requestHash): StockDeductionResult {
            $this->handlerTiming->mark('deduction.transaction_opened');
            $this->lockOperationId($operationId);
            $this->handlerTiming->mark('deduction.operation_lock_acquired');

            $existingOperation = $this->operationRepository->findOneByOperationId($operationId);
            $this->handlerTiming->mark('deduction.existing_operation_loaded');

            if ($existingOperation !== null) {
                if ($existingOperation->getType() !== InventoryOperation::TYPE_STOCK_DEDUCTION || $existingOperation->getRequestHash() !== $requestHash) {
                    throw new InventoryOperationConflictException("operation_id already used with a different request.");
                }

                return StockDeductionResult::fromPayload($existingOperation->getResponsePayload());
            }

            $deductionPlans = [];
            $stockRowsToUpdate = [];

            foreach ($normalizedItems as $item) {
                $deductionPlans[] = $item->storeId === null
                    ? $this->createAutomaticDeductionPlan($item, $stockRowsToUpdate)
                    : $this->createStoreDeductionPlan($item, $stockRowsToUpdate);
            }
            $this->handlerTiming->mark('deduction.stock_rows_loaded_and_planned');

            foreach ($stockRowsToUpdate as [$stockRow, $deductedQuantity]) {
                $stockRow->setStock(($stockRow->getStock() ?? 0) - $deductedQuantity);
            }
            $this->handlerTiming->mark('deduction.stock_rows_updated_in_memory');

            $mergedDeductions = $this->mergeProductDeductionsByProductId($deductionPlans);
            $this->handlerTiming->mark('deduction.product_deductions_merged');
            $productSnapshotIds = $this->createProductSnapshots($operationId, $mergedDeductions);
            $this->handlerTiming->mark('deduction.snapshots_completed');
            $result = new StockDeductionResult(
                $operationId,
                $this->attachProductSnapshotIds($mergedDeductions, $productSnapshotIds),
            );
            $this->handlerTiming->mark('deduction.result_built');

            $this->operationRepository->addDeductionOperation(
                $operationId,
                $requestHash,
                $result->toPayload(),
            );
            $this->handlerTiming->mark('deduction.operation_persist_scheduled');
            $this->entityManager->flush();
            $this->handlerTiming->mark('deduction.final_flush_completed');

            return $result;
        });
        $this->handlerTiming->mark('deduction.transaction_committed');

        return $result;
    }

    /**
     * @param list<StockDeductionRequestItem> $items
     * @return list<StockDeductionRequestItem>
     */
    private function normalizeItems(string $operationId, array $items): array
    {
        if ($operationId === "") {
            throw new InvalidInventoryDeductionRequestException("operation_id must not be empty.");
        }

        if ($items === []) {
            throw new InvalidInventoryDeductionRequestException("items must not be empty.");
        }

        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidInventoryDeductionRequestException("items limit exceeded.");
        }

        $normalized = [];
        $productSelectors = [];

        foreach ($items as $item) {
            if ($item->productId <= 0) {
                throw new InvalidInventoryDeductionRequestException("product_id must be greater than zero.");
            }

            if ($item->requestedQuantity <= 0) {
                throw new InvalidInventoryDeductionRequestException("requested_quantity must be greater than zero.");
            }

            if ($item->storeId !== null && $item->storeId <= 0) {
                throw new InvalidInventoryDeductionRequestException("store_id must be greater than zero when provided.");
            }

            $selector = $item->storeId === null ? "auto" : "store";

            if (isset($productSelectors[$item->productId]) && $productSelectors[$item->productId] !== $selector) {
                throw new InvalidInventoryDeductionRequestException("product_id cannot be requested with store_id and without store_id in the same request.");
            }

            $productSelectors[$item->productId] = $selector;
            $key = sprintf("%d:%s", $item->productId, $item->storeId === null ? "*" : (string) $item->storeId);

            if (!isset($normalized[$key])) {
                $normalized[$key] = new StockDeductionRequestItem($item->productId, 0, $item->storeId);
            }

            $normalized[$key] = new StockDeductionRequestItem(
                $item->productId,
                $normalized[$key]->requestedQuantity + $item->requestedQuantity,
                $item->storeId,
            );
        }

        $normalizedItems = array_values($normalized);
        usort($normalizedItems, static function (StockDeductionRequestItem $left, StockDeductionRequestItem $right): int {
            return [$left->productId, $left->storeId ?? 0] <=> [$right->productId, $right->storeId ?? 0];
        });

        return $normalizedItems;
    }

    /**
     * @param list<StockDeductionRequestItem> $items
     */
    private function createRequestHash(array $items): string
    {
        $payload = [];

        foreach ($items as $item) {
            $payload[] = [
                "productId" => $item->productId,
                "requestedQuantity" => $item->requestedQuantity,
                "storeId" => $item->storeId,
            ];
        }

        return hash("sha256", json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function lockOperationId(string $operationId): void
    {
        $this->connection->executeQuery("SELECT pg_advisory_xact_lock(hashtextextended(:operation_id, 0))", [
            "operation_id" => $operationId,
        ]);
    }

    /**
     * @param list<array{0: StoresElementsStocks, 1: int}> $stockRowsToUpdate
     */
    private function createStoreDeductionPlan(StockDeductionRequestItem $item, array &$stockRowsToUpdate): ProductStockDeduction
    {
        if ($item->storeId === null) {
            throw new InvalidInventoryDeductionRequestException("store_id must be provided.");
        }

        $this->handlerTiming->mark('store_plan.product_lookup_started');
        $productExists = $this->catalogElementsRepository->existsById($item->productId);
        $this->handlerTiming->mark('store_plan.product_lookup_completed');
        if (!$productExists) {
            throw new InventoryDeductionNotFoundException("product not found.");
        }

        $storeExists = $this->storesRepository->existsById($item->storeId);
        $this->handlerTiming->mark('store_plan.store_lookup_completed');
        if (!$storeExists) {
            throw new InventoryDeductionNotFoundException("store not found.");
        }

        $stockRow = $this->stocksRepository->findOneForProductStoreWithWriteLock($item->productId, $item->storeId);
        $this->handlerTiming->mark('store_plan.stock_row_loaded');

        if ($stockRow === null) {
            throw new InventoryDeductionNotFoundException("stock row not found.");
        }

        $stock = $stockRow->getStock() ?? 0;

        if ($stock < $item->requestedQuantity) {
            throw new InsufficientInventoryStockException("insufficient stock.");
        }

        $stockRowsToUpdate[] = [$stockRow, $item->requestedQuantity];

        return new ProductStockDeduction(
            $item->productId,
            $item->requestedQuantity,
            [new StoreStockDeduction($item->storeId, $item->requestedQuantity)],
        );
    }

    /**
     * @param list<array{0: StoresElementsStocks, 1: int}> $stockRowsToUpdate
     */
    private function createAutomaticDeductionPlan(StockDeductionRequestItem $item, array &$stockRowsToUpdate): ProductStockDeduction
    {
        $this->handlerTiming->mark('auto_plan.product_lookup_started');
        $productExists = $this->catalogElementsRepository->existsById($item->productId);
        $this->handlerTiming->mark('auto_plan.product_lookup_completed');
        if (!$productExists) {
            throw new InventoryDeductionNotFoundException("product not found.");
        }

        $stockRows = $this->stocksRepository->findPositiveForProductWithWriteLock($item->productId);
        $this->handlerTiming->mark('auto_plan.stock_rows_loaded');
        $remainingQuantityToDeduct = $item->requestedQuantity;
        $storeDeductions = [];
        foreach ($stockRows as $stockRow) {
            if ($remainingQuantityToDeduct <= 0) {
                break;
            }

            $stock = $stockRow->getStock() ?? 0;
            $storeId = $stockRow->getStore()?->getId();

            if ($storeId === null) {
                throw new InventoryDeductionNotFoundException("store not found.");
            }

            $deductedQuantity = min($remainingQuantityToDeduct, $stock);

            if ($deductedQuantity <= 0) {
                continue;
            }

            $stockRowsToUpdate[] = [$stockRow, $deductedQuantity];
            $storeDeductions[] = new StoreStockDeduction($storeId, $deductedQuantity);
            $remainingQuantityToDeduct -= $deductedQuantity;
        }

        if ($remainingQuantityToDeduct > 0) {
            throw new InsufficientInventoryStockException("insufficient stock.");
        }

        return new ProductStockDeduction($item->productId, $item->requestedQuantity, $storeDeductions);
    }

    /**
     * @param list<ProductStockDeduction> $products
     * @return array<int, int>
     */
    private function createProductSnapshots(string $operationId, array $products): array
    {
        $this->handlerTiming->mark('snapshots.entered');
        $snapshots = [];

        foreach ($products as $productDeduction) {
            $this->handlerTiming->mark('snapshots.source_query_started');
            $catalogElement = $this->catalogElementsRepository->findOneForInventorySnapshot($productDeduction->getProductId());
            $this->handlerTiming->mark('snapshots.source_loaded');
            $sourceProduct = $catalogElement?->getProduct();

            if ($catalogElement === null || $sourceProduct === null) {
                throw new InventoryDeductionNotFoundException("product not found.");
            }

            $snapshots[$productDeduction->getProductId()] = $this->productSnapshotRepository->createFromCatalogElement(
                $catalogElement,
                $operationId,
            );
            $this->handlerTiming->mark('snapshots.entity_persist_scheduled');
        }

        $this->handlerTiming->mark('snapshots.flush_started');
        $this->entityManager->flush();
        $this->handlerTiming->mark('snapshots.flush_completed');

        $snapshotIds = [];

        foreach ($snapshots as $productId => $snapshot) {
            $snapshotId = $snapshot->getId();

            if ($snapshotId === null) {
                throw new \RuntimeException("Product snapshot id was not generated.");
            }

            $snapshotIds[(int) $productId] = $snapshotId;
        }
        $this->handlerTiming->mark('snapshots.ids_collected');

        return $snapshotIds;
    }

    /**
     * @param list<ProductStockDeduction> $products
     * @param array<int, int>             $productSnapshotIds
     * @return list<ProductStockDeduction>
     */
    private function attachProductSnapshotIds(array $products, array $productSnapshotIds): array
    {
        $result = [];

        foreach ($products as $product) {
            $result[] = $product->withProductSnapshotId(
                $productSnapshotIds[$product->getProductId()] ?? throw new \RuntimeException("Product snapshot id is missing."),
            );
        }

        return $result;
    }

    /**
     * @param list<ProductStockDeduction> $products
     * @return list<ProductStockDeduction>
     */
    private function mergeProductDeductionsByProductId(array $products): array
    {
        $mergedByProductId = [];

        foreach ($products as $product) {
            $productId = $product->getProductId();

            if (!isset($mergedByProductId[$productId])) {
                $mergedByProductId[$productId] = $product;
                continue;
            }

            $mergedByProductId[$productId]->addDeduction($product);
        }

        ksort($mergedByProductId);

        return array_values($mergedByProductId);
    }
}
