<?php

namespace App\DataFixtures;

use App\Grpc\CatalogInventoryClient;
use App\Order\OrderStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
//docker compose run --rm cart-cli php -d memory_limit=512M bin/console doctrine:fixtures:load --no-debug
class AppFixtures extends Fixture
{
    // Number of carts to create from the user pool returned by Main Service.
    private const CART_COUNT = 100;

    // Number of orders to create from the user pool returned by Main Service.
    private const ORDER_COUNT = 100000;

    // Maximum number of unique catalog elements added to each generated cart.
    private const MAX_CART_ITEMS_PER_CART = 10;

    // Number of catalog elements converted into order items for each generated order.
    private const ORDER_ITEMS_PER_ORDER = 10;

    // Upper quantity bound per item; the product stock from Catalog Service is still respected.
    private const MAX_ITEM_QUANTITY = 5;

    // Converts decimal money values to minor units for integer-safe fixture calculations.
    private const MONEY_SCALE_FACTOR = 100;

    // Maximum catalog elements requested before filtering unavailable products locally.
    private const CATALOG_PRODUCT_LIMIT = 50;

    // Size of the user pool requested from Main Service; fixtures sample owners from this pool.
    private const USER_LIMIT = 100;

    // Number of root records inserted per transaction.
    private const WRITE_BATCH_SIZE = 1000;

    // Maximum amount of rows inserted by one SQL statement to stay below PostgreSQL parameter limits.
    private const SQL_ROW_BATCH_SIZE = 5000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CatalogInventoryClient $catalogInventoryClient,
        private readonly string $catalogServiceBaseUrl,
        private readonly string $mainServiceBaseUrl,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf("%s requires Doctrine ORM EntityManager.", self::class));
        }

        $ownerIds = $this->fetchOwnerIds();
        $cartOwnerIds = $this->createCartOwnerIds($ownerIds);
        $cartItemCounts = $this->createCartItemCounts(\count($cartOwnerIds));

        $products = $this->fetchAvailableCatalogProducts();
        $this->assertEnoughProducts($products);

        $snapshotsByCatalogElementId = $this->fetchSnapshotsByCatalogElementId($products);
        $connection = $manager->getConnection();
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");

        $this->insertCarts($connection, $cartOwnerIds, $cartItemCounts, $products, $now);
        $this->insertOrders($connection, $ownerIds, $products, $snapshotsByCatalogElementId, $now);
    }

    /**
     * @return list<int>
     */
    private function fetchOwnerIds(): array
    {
        $payload = $this->requestJson($this->mainServiceBaseUrl, "/api/v1/users", [
            "limit" => (string) self::USER_LIMIT,
            "sort" => "id",
            "direction" => "ASC",
        ]);

        $items = $payload["items"] ?? null;

        if (!\is_array($items) || $items === []) {
            throw new \RuntimeException("Main Service returned no users.");
        }

        $ownerIds = [];

        foreach ($items as $item) {
            if (!\is_array($item) || !\is_int($item["id"] ?? null)) {
                continue;
            }

            $ownerIds[] = $item["id"];
        }

        if ($ownerIds === []) {
            throw new \RuntimeException("Main Service returned no valid user identifiers.");
        }

        return $ownerIds;
    }

    /**
     * @return array<int, array{id: int, totalStock: int, price: int}>
     */
    private function fetchAvailableCatalogProducts(): array
    {
        $payload = $this->requestJson($this->catalogServiceBaseUrl, "/api/catalog/elements", [
            "active" => "true",
            "limit" => (string) self::CATALOG_PRODUCT_LIMIT,
        ]);

        $items = $payload["items"] ?? null;

        if (!\is_array($items) || $items === []) {
            throw new \RuntimeException("Catalog Service returned no catalog elements.");
        }

        $products = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $id = $item["id"] ?? null;
            $totalStock = $item["totalStock"] ?? null;
            $price = $this->extractActiveBasePrice($item);

            if (!\is_int($id) || !\is_int($totalStock) || $totalStock <= 0 || $price === null) {
                continue;
            }

            $products[] = [
                "id" => $id,
                "totalStock" => $totalStock,
                "price" => $price,
            ];
        }

        if ($products === []) {
            throw new \RuntimeException("Catalog Service returned no active products with positive stock and active price.");
        }

        return $products;
    }

    /**
     * @param array<int, array{id: int, totalStock: int, price: int}> $products
     *
     * @return array<int, int>
     */
    private function fetchSnapshotsByCatalogElementId(array $products): array
    {
        $deductItems = [];

        foreach ($products as $product) {
            $deductItems[] = [
                "productId" => $product["id"],
                "quantity" => 1,
            ];
        }

        $operationId = "cart-fixtures-snapshot-map-" . substr(hash("sha256", implode(",", array_column($products, "id"))), 0, 16);
        $result = $this->catalogInventoryClient->deductStocks($operationId, $deductItems);
        $snapshotsByCatalogElementId = [];

        foreach ($result->products as $productDeduction) {
            if ($productDeduction->productSnapshotId <= 0) {
                throw new \RuntimeException(sprintf("Catalog Service returned no product snapshot for catalog element %d.", $productDeduction->productId));
            }

            $snapshotsByCatalogElementId[$productDeduction->productId] = $productDeduction->productSnapshotId;
        }

        foreach ($products as $product) {
            if (!isset($snapshotsByCatalogElementId[$product["id"]])) {
                throw new \RuntimeException(sprintf("Catalog Service returned no product snapshot for catalog element %d.", $product["id"]));
            }
        }

        return $snapshotsByCatalogElementId;
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $baseUrl, string $path, array $query): array
    {
        $url = sprintf("%s%s?%s", rtrim($baseUrl, "/"), $path, http_build_query($query));

        try {
            $response = $this->httpClient->request("GET", $url);
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(sprintf("Service request failed with HTTP %d: %s", $statusCode, $url));
            }

            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException(sprintf("Service is unavailable: %s", $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($payload)) {
            throw new \RuntimeException(sprintf("Service returned invalid JSON payload: %s", $url));
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractActiveBasePrice(array $item): ?int
    {
        $prices = $item["productPrices"] ?? null;

        if (!\is_array($prices)) {
            return null;
        }

        $fallbackPrice = null;

        foreach ($prices as $priceRow) {
            if (!\is_array($priceRow) || ($priceRow["active"] ?? false) !== true || !\is_int($priceRow["price"] ?? null)) {
                continue;
            }

            if ($fallbackPrice === null) {
                $fallbackPrice = $priceRow["price"];
            }

            $priceType = $priceRow["priceType"] ?? null;
            $priceTypeCode = \is_array($priceType) ? ($priceType["code"] ?? null) : null;

            if ($priceTypeCode === "BASE") {
                return $priceRow["price"];
            }
        }

        return $fallbackPrice;
    }

    /**
     * @param list<int> $ownerIds
     *
     * @return list<int>
     */
    private function createCartOwnerIds(array $ownerIds): array
    {
        $cartOwnerIds = $ownerIds;
        shuffle($cartOwnerIds);

        return \array_slice($cartOwnerIds, 0, min(self::CART_COUNT, \count($cartOwnerIds)));
    }

    /**
     * @param list<int> $ownerIds
     */
    private function pickOwnerId(array $ownerIds): int
    {
        return $ownerIds[random_int(0, \count($ownerIds) - 1)];
    }

    private function pickOrderStatus(): OrderStatus
    {
        $statuses = OrderStatus::cases();

        return $statuses[random_int(0, \count($statuses) - 1)];
    }

    /**
     * @param array<int, array{id: int, totalStock: int, price: int}> $products
     */
    private function assertEnoughProducts(array $products): void
    {
        $requiredProductCount = max(self::MAX_CART_ITEMS_PER_CART, self::ORDER_ITEMS_PER_ORDER);

        if (\count($products) < $requiredProductCount) {
            throw new \RuntimeException(sprintf(
                "Catalog Service returned %d usable products, but %d are required for cart fixtures.",
                \count($products),
                $requiredProductCount,
            ));
        }
    }

    /**
     * @return list<int>
     */
    private function createCartItemCounts(int $cartCount): array
    {
        $cartItemCounts = [];

        for ($i = 0; $i < $cartCount; $i++) {
            $cartItemCounts[] = random_int(1, self::MAX_CART_ITEMS_PER_CART);
        }

        return $cartItemCounts;
    }

    /**
     * @param array<int, array{id: int, totalStock: int, price: int}> $products
     *
     * @return list<array{id: int, totalStock: int, price: int}>
     */
    private function pickProducts(array $products, int $count): array
    {
        $offsets = \array_rand($products, $count);

        if (!\is_array($offsets)) {
            $offsets = [$offsets];
        }

        $selectedProducts = [];

        foreach ($offsets as $offset) {
            $selectedProducts[] = $products[$offset];
        }

        return $selectedProducts;
    }

    /**
     * @param list<int> $cartOwnerIds
     * @param list<int> $cartItemCounts
     * @param array<int, array{id: int, totalStock: int, price: int}> $products
     */
    private function insertCarts(Connection $connection, array $cartOwnerIds, array $cartItemCounts, array $products, string $now): void
    {
        foreach (array_chunk($cartOwnerIds, self::WRITE_BATCH_SIZE, preserve_keys: true) as $ownerChunk) {
            $connection->transactional(function () use ($connection, $ownerChunk, $cartItemCounts, $products, $now): void {
                $cartRows = [];

                foreach ($ownerChunk as $ownerId) {
                    $cartRows[] = [
                        "owner_id" => $ownerId,
                        "created_at" => $now,
                        "updated_at" => $now,
                    ];
                }

                $insertedCarts = $this->insertRowsReturning($connection, "carts", ["owner_id", "created_at", "updated_at"], $cartRows, ["id"]);
                $cartItemRows = [];
                $insertedCartOffset = 0;

                foreach ($ownerChunk as $ownerOffset => $ownerId) {
                    $cartId = $this->requireReturnedId($insertedCarts[$insertedCartOffset] ?? null, "cart");
                    $insertedCartOffset++;

                    foreach ($this->pickProducts($products, $cartItemCounts[$ownerOffset]) as $sortOffset => $product) {
                        $cartItemRows[] = [
                            "cart_id" => $cartId,
                            "catalog_element_id" => $product["id"],
                            "quantity" => $this->createQuantity($product["totalStock"]),
                            "sort" => ($sortOffset + 1) * 100,
                            "created_at" => $now,
                            "updated_at" => $now,
                        ];
                    }
                }

                $this->insertRows($connection, "cart_items", ["cart_id", "catalog_element_id", "quantity", "sort", "created_at", "updated_at"], $cartItemRows);
            });
        }
    }

    /**
     * @param list<int> $ownerIds
     * @param array<int, array{id: int, totalStock: int, price: int}> $products
     * @param array<int, int> $snapshotsByCatalogElementId
     */
    private function insertOrders(Connection $connection, array $ownerIds, array $products, array $snapshotsByCatalogElementId, string $now): void
    {
        $createdOrders = 0;

        while ($createdOrders < self::ORDER_COUNT) {
            $batchSize = min(self::WRITE_BATCH_SIZE, self::ORDER_COUNT - $createdOrders);

            $connection->transactional(function () use ($connection, $ownerIds, $products, $snapshotsByCatalogElementId, $now, $batchSize): void {
                $orderRows = [];
                $orderItemsByOffset = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $totalMinorUnits = 0;
                    $orderItems = [];

                    foreach ($this->pickProducts($products, self::ORDER_ITEMS_PER_ORDER) as $sortOffset => $product) {
                        $quantity = $this->createQuantity($product["totalStock"]);
                        $lineTotalMinorUnits = $product["price"] * $quantity;
                        $totalMinorUnits += $lineTotalMinorUnits;

                        $orderItems[] = [
                            "product_snapshot_id" => $snapshotsByCatalogElementId[$product["id"]],
                            "quantity" => $quantity,
                            "sort" => ($sortOffset + 1) * 100,
                            "unit_price" => $this->formatMoney($product["price"]),
                            "unit_discount" => "0.00",
                            "final_unit_price" => $this->formatMoney($product["price"]),
                            "line_total" => $this->formatMoney($lineTotalMinorUnits),
                            "created_at" => $now,
                        ];
                    }

                    $orderRows[] = [
                        "owner_id" => $this->pickOwnerId($ownerIds),
                        "status" => $this->pickOrderStatus()->value,
                        "total_price" => $this->formatMoney($totalMinorUnits),
                        "total_discount" => "0.00",
                        "final_price" => $this->formatMoney($totalMinorUnits),
                        "created_at" => $now,
                        "updated_at" => $now,
                    ];
                    $orderItemsByOffset[] = $orderItems;
                }

                $insertedOrders = $this->insertRowsReturning($connection, "orders", ["owner_id", "status", "total_price", "total_discount", "final_price", "created_at", "updated_at"], $orderRows, ["id"]);
                $orderItemRows = [];

                foreach ($orderItemsByOffset as $orderOffset => $orderItems) {
                    $orderId = $this->requireReturnedId($insertedOrders[$orderOffset] ?? null, "order");

                    foreach ($orderItems as $orderItem) {
                        $orderItemRows[] = ["order_id" => $orderId] + $orderItem;
                    }
                }

                $this->insertRows($connection, "order_items", ["order_id", "product_snapshot_id", "quantity", "sort", "unit_price", "unit_discount", "final_unit_price", "line_total", "created_at"], $orderItemRows);
            });

            $createdOrders += $batchSize;
        }
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, int|string>> $rows
     */
    private function insertRows(Connection $connection, string $tableName, array $columns, array $rows): void
    {
        foreach (array_chunk($rows, self::SQL_ROW_BATCH_SIZE) as $rowChunk) {
            $this->executeInsert($connection, $tableName, $columns, $rowChunk);
        }
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, int|string>> $rows
     * @param list<string> $returningColumns
     *
     * @return list<array<string, mixed>>
     */
    private function insertRowsReturning(Connection $connection, string $tableName, array $columns, array $rows, array $returningColumns): array
    {
        $insertedRows = [];

        foreach (array_chunk($rows, self::SQL_ROW_BATCH_SIZE) as $rowChunk) {
            foreach ($this->executeInsert($connection, $tableName, $columns, $rowChunk, $returningColumns) as $insertedRow) {
                $insertedRows[] = $insertedRow;
            }
        }

        return $insertedRows;
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, int|string>> $rows
     * @param list<string> $returningColumns
     *
     * @return list<array<string, mixed>>
     */
    private function executeInsert(Connection $connection, string $tableName, array $columns, array $rows, array $returningColumns = []): array
    {
        if ($rows === []) {
            return [];
        }

        $parameters = [];
        $placeholders = [];

        foreach ($rows as $row) {
            $rowPlaceholders = [];

            foreach ($columns as $column) {
                $rowPlaceholders[] = "?";
                $parameters[] = $row[$column];
            }

            $placeholders[] = sprintf("(%s)", implode(", ", $rowPlaceholders));
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $tableName,
            implode(", ", $columns),
            implode(", ", $placeholders),
        );

        if ($returningColumns !== []) {
            $sql .= sprintf(" RETURNING %s", implode(", ", $returningColumns));

            return $connection->executeQuery($sql, $parameters)->fetchAllAssociative();
        }

        $connection->executeStatement($sql, $parameters);

        return [];
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private function requireReturnedId(?array $row, string $recordName): int
    {
        $id = $row["id"] ?? null;

        if (\is_int($id)) {
            return $id;
        }

        if (\is_string($id) && ctype_digit($id)) {
            return (int) $id;
        }

        throw new \RuntimeException(sprintf("Database did not return %s identifier.", $recordName));
    }

    private function createQuantity(int $totalStock): int
    {
        return random_int(1, min($totalStock, self::MAX_ITEM_QUANTITY));
    }

    private function formatMoney(int $minorUnits): string
    {
        return number_format($minorUnits / self::MONEY_SCALE_FACTOR, 2, ".", "");
    }
}
