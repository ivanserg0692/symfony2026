<?php

namespace App\Grpc;

use App\Entity\CatalogElements;
use App\Repository\CatalogElementsRepository;
use Grpc\Catalog\V1\CheckStockRequest;
use Grpc\Catalog\V1\CheckStockResponse;
use Grpc\Catalog\V1\InventoryServiceInterface;
use Grpc\Catalog\V1\StoreStock;
use Spiral\RoadRunner\GRPC;

final readonly class InventoryService implements InventoryServiceInterface
{
    public function __construct(
        private CatalogElementsRepository $catalogElementsRepository
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
}
