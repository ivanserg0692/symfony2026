<?php

namespace App\Tests\Integration\Inventory;

use App\Entity\CatalogElements;
use App\Entity\Stores;
use App\Entity\StoresElementsStocks;
use App\Inventory\StockDeductionRequestItem;
use App\Inventory\InsufficientInventoryStockException;
use App\Inventory\InvalidInventoryDeductionRequestException;
use App\Inventory\InventoryDeductionNotFoundException;
use App\Inventory\InventoryDeductionService;
use App\Inventory\InventoryOperationConflictException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InventoryDeductionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private InventoryDeductionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(InventoryDeductionService::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
        }

        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    public function testDeductsSingleProductFromExplicitStore(): void
    {
        $store = $this->createStore('store-1');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store, 8);

        $result = $this->service->deduct('op-explicit', [
            new StockDeductionRequestItem($product->getId(), 5, $store->getId()),
        ]);

        self::assertSame(3, $this->stock($product, $store));
        self::assertSame('op-explicit', $result->operationId);
        self::assertSame($product->getId(), $result->products[0]->productId);
        self::assertSame(5, $result->products[0]->totalDeductedQuantity);
        self::assertSame($store->getId(), $result->products[0]->stores[0]->storeId);
        self::assertSame(5, $result->products[0]->stores[0]->deductedQuantity);
    }

    public function testExplicitStoreInsufficientStockDoesNotUseOtherStore(): void
    {
        $store1 = $this->createStore('store-1');
        $store2 = $this->createStore('store-2');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store1, 4);
        $this->addStock($product, $store2, 10);

        $this->expectException(InsufficientInventoryStockException::class);

        try {
            $this->service->deduct('op-insufficient-explicit', [
                new StockDeductionRequestItem($product->getId(), 5, $store1->getId()),
            ]);
        } finally {
            self::assertSame(4, $this->stock($product, $store1));
            self::assertSame(10, $this->stock($product, $store2));
        }
    }

    public function testAutomaticallyDeductsFromSingleStore(): void
    {
        $store = $this->createStore('store-1');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store, 9);

        $result = $this->service->deduct('op-auto-one', [
            new StockDeductionRequestItem($product->getId(), 6, null),
        ]);

        self::assertSame(3, $this->stock($product, $store));
        self::assertSame($store->getId(), $result->products[0]->stores[0]->storeId);
        self::assertSame(6, $result->products[0]->stores[0]->deductedQuantity);
    }

    public function testAutomaticallySplitsDeductionBetweenStoresInStoreIdOrder(): void
    {
        $store1 = $this->createStore('store-1');
        $store2 = $this->createStore('store-2');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store1, 5);
        $this->addStock($product, $store2, 10);

        $result = $this->service->deduct('op-auto-split', [
            new StockDeductionRequestItem($product->getId(), 7, null),
        ]);

        self::assertSame(0, $this->stock($product, $store1));
        self::assertSame(8, $this->stock($product, $store2));
        self::assertSame([
            [$store1->getId(), 5],
            [$store2->getId(), 2],
        ], $this->storePairs($result));
    }

    public function testDeductsMultipleProductsInOneRequest(): void
    {
        $store = $this->createStore('store-1');
        $product1 = $this->createProduct('product-1');
        $product2 = $this->createProduct('product-2');
        $this->addStock($product1, $store, 5);
        $this->addStock($product2, $store, 7);

        $result = $this->service->deduct('op-multi-products', [
            new StockDeductionRequestItem($product2->getId(), 2, $store->getId()),
            new StockDeductionRequestItem($product1->getId(), 3, $store->getId()),
        ]);

        self::assertSame(2, $this->stock($product1, $store));
        self::assertSame(5, $this->stock($product2, $store));
        self::assertSame([$product1->getId(), $product2->getId()], array_map(static fn ($product): int => $product->productId, $result->products));
    }

    public function testRollsBackAllChangesWhenOneItemCannotBeDeducted(): void
    {
        $store = $this->createStore('store-1');
        $product1 = $this->createProduct('product-1');
        $product2 = $this->createProduct('product-2');
        $this->addStock($product1, $store, 5);
        $this->addStock($product2, $store, 1);

        $this->expectException(InsufficientInventoryStockException::class);

        try {
            $this->service->deduct('op-rollback', [
                new StockDeductionRequestItem($product1->getId(), 3, $store->getId()),
                new StockDeductionRequestItem($product2->getId(), 2, $store->getId()),
            ]);
        } finally {
            self::assertSame(5, $this->stock($product1, $store));
            self::assertSame(1, $this->stock($product2, $store));
        }
    }

    public function testProductNotFoundFails(): void
    {
        $this->expectException(InventoryDeductionNotFoundException::class);

        $this->service->deduct('op-product-not-found', [
            new StockDeductionRequestItem(999999, 1, null),
        ]);
    }

    public function testExplicitStoreNotFoundFails(): void
    {
        $product = $this->createProduct('product-1');

        $this->expectException(InventoryDeductionNotFoundException::class);

        $this->service->deduct('op-store-not-found', [
            new StockDeductionRequestItem($product->getId(), 1, 999999),
        ]);
    }

    public function testInvalidRequestedQuantityFails(): void
    {
        $product = $this->createProduct('product-1');

        $this->expectException(InvalidInventoryDeductionRequestException::class);

        $this->service->deduct('op-invalid-qty', [
            new StockDeductionRequestItem($product->getId(), 0, null),
        ]);
    }

    public function testEmptyItemsFails(): void
    {
        $this->expectException(InvalidInventoryDeductionRequestException::class);

        $this->service->deduct('op-empty', []);
    }

    public function testRepeatedRequestWithSameOperationIdDoesNotDeductAgain(): void
    {
        $store = $this->createStore('store-1');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store, 10);
        $items = [new StockDeductionRequestItem($product->getId(), 4, $store->getId())];

        $first = $this->service->deduct('op-idempotent', $items);
        $second = $this->service->deduct('op-idempotent', $items);

        self::assertSame(6, $this->stock($product, $store));
        self::assertSame($first->toPayload(), $second->toPayload());
    }

    public function testSameOperationIdWithDifferentRequestFails(): void
    {
        $store = $this->createStore('store-1');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store, 10);

        $this->service->deduct('op-conflict', [
            new StockDeductionRequestItem($product->getId(), 4, $store->getId()),
        ]);

        $this->expectException(InventoryOperationConflictException::class);

        $this->service->deduct('op-conflict', [
            new StockDeductionRequestItem($product->getId(), 5, $store->getId()),
        ]);
    }

    public function testDuplicatePositionsAreNormalized(): void
    {
        $store = $this->createStore('store-1');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store, 10);

        $result = $this->service->deduct('op-duplicates', [
            new StockDeductionRequestItem($product->getId(), 2, $store->getId()),
            new StockDeductionRequestItem($product->getId(), 3, $store->getId()),
        ]);

        self::assertSame(5, $this->stock($product, $store));
        self::assertSame(5, $result->products[0]->totalDeductedQuantity);
    }

    public function testAmbiguousDuplicatePositionsAreRejected(): void
    {
        $store = $this->createStore('store-1');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store, 10);

        $this->expectException(InvalidInventoryDeductionRequestException::class);

        $this->service->deduct('op-ambiguous', [
            new StockDeductionRequestItem($product->getId(), 2, null),
            new StockDeductionRequestItem($product->getId(), 3, $store->getId()),
        ]);
    }

    public function testResponseContainsActualStoreDistribution(): void
    {
        $store1 = $this->createStore('store-1');
        $store2 = $this->createStore('store-2');
        $product = $this->createProduct('product-1');
        $this->addStock($product, $store1, 5);
        $this->addStock($product, $store2, 10);

        $result = $this->service->deduct('op-response-distribution', [
            new StockDeductionRequestItem($product->getId(), 7, null),
        ]);

        self::assertSame([
            [$store1->getId(), 5],
            [$store2->getId(), 2],
        ], $this->storePairs($result));
    }

    private function createStore(string $slug): Stores
    {
        $store = (new Stores())
            ->setName($slug)
            ->setSlug($slug)
            ->setActive(true);

        $this->entityManager->persist($store);
        $this->entityManager->flush();

        return $store;
    }

    private function createProduct(string $slug): CatalogElements
    {
        $product = (new CatalogElements())
            ->setName($slug)
            ->setSlug($slug)
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'))
            ->setCreatedBy(1)
            ->setSort(100);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function addStock(CatalogElements $product, Stores $store, int $stock): void
    {
        $stockRow = (new StoresElementsStocks())
            ->setElement($product)
            ->setStore($store)
            ->setStock($stock);

        $this->entityManager->persist($stockRow);
        $this->entityManager->flush();
    }

    private function stock(CatalogElements $product, Stores $store): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT stock FROM stores_elements_stocks WHERE element_id = :product_id AND store_id = :store_id',
            [
                'product_id' => $product->getId(),
                'store_id' => $store->getId(),
            ],
        );
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function storePairs($result): array
    {
        return array_map(
            static fn ($store): array => [$store->storeId, $store->deductedQuantity],
            $result->products[0]->stores,
        );
    }
}
