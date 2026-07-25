<?php

namespace App\Tests\Functional;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Grpc\CatalogDeductStocksResult;
use App\Grpc\CatalogInventoryClient;
use App\Grpc\CatalogProductDeduction;
use App\Grpc\CatalogProductPrice;
use App\Grpc\CatalogStockResponse;
use App\Grpc\CatalogStoreDeduction;
use App\Grpc\CatalogStoreStock;
use App\Grpc\InsufficientStockException;
use App\Grpc\InventoryItemNotFoundException;
use App\Grpc\InventoryServiceUnavailableException;
use App\Order\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Stamp\StampInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    private CatalogInventoryClient $catalogInventoryClient;
    private TraceableMessageBus $messageBus;
    private RecordingMessageBus $recordingMessageBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
        }

        $schemaTool->createSchema($metadata);

        $this->setCatalogStockAvailable(true);
        $this->setCatalogProductPrices([]);
        $this->setCatalogDeductFailure(null);
        static::getContainer()->set(MessageBusInterface::class, $this->messageBus());
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function requestJson(string $method, string $uri, ?int $ownerId = null, ?array $body = null): void
    {
        $server = ["CONTENT_TYPE" => "application/json"];

        if ($ownerId !== null) {
            $server["HTTP_X_USER_ID"] = (string) $ownerId;
        }

        $this->client->request($method, $uri, server: $server, content: $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function setCatalogStockAvailable(bool $available): void
    {
        $this->catalogInventoryClient()->setProductFound(true);
        $this->catalogInventoryClient()->setFail(false);
        $this->catalogInventoryClient()->setAvailableQuantity($available ? 1000000 : 0);
    }

    protected function setCatalogStockAvailableQuantity(int $availableQuantity): void
    {
        $this->catalogInventoryClient()->setProductFound(true);
        $this->catalogInventoryClient()->setFail(false);
        $this->catalogInventoryClient()->setAvailableQuantity($availableQuantity);
    }

    protected function setCatalogProductFound(bool $found): void
    {
        $this->catalogInventoryClient()->setProductFound($found);
    }

    protected function setCatalogInventoryFailure(bool $fail): void
    {
        $this->catalogInventoryClient()->setFail($fail);
    }

    /**
     * @param array<int, CatalogProductPrice> $pricesByProductId
     */
    protected function setCatalogProductPrices(array $pricesByProductId): void
    {
        $this->catalogInventoryClient()->setProductPrices($pricesByProductId);
    }

    protected function setCatalogDeductFailure(?\Throwable $failure): void
    {
        $this->catalogInventoryClient()->setDeductFailure($failure);
    }

    /**
     * @param list<CatalogProductDeduction>|null $deductions
     */
    protected function setCatalogDeductResult(?array $deductions): void
    {
        $this->catalogInventoryClient()->setDeductResult($deductions);
    }

    /**
     * @return list<array{productId: int, requestedQuantity: int}>
     */
    protected function catalogStockRequests(): array
    {
        return $this->catalogInventoryClient()->stockRequests();
    }

    /**
     * @return list<int>
     */
    protected function catalogPriceRequests(): array
    {
        return $this->catalogInventoryClient()->priceRequests();
    }

    /**
     * @return list<array{operationId: string, items: list<array{productId: int, quantity: int, storeId?: int|null}>}>
     */
    protected function catalogDeductRequests(): array
    {
        return $this->catalogInventoryClient()->deductRequests();
    }

    /**
     * @return list<object>
     */
    protected function dispatchedMessages(): array
    {
        return $this->recordingMessageBus()->messages();
    }

    private function catalogInventoryClient(): CatalogInventoryClient
    {
        if (!isset($this->catalogInventoryClient)) {
            $this->catalogInventoryClient = new class extends CatalogInventoryClient {
                private int $availableQuantity = 1000000;
                private bool $productFound = true;
                private bool $fail = false;
                private ?\Throwable $deductFailure = null;

                /**
                 * @var array<int, CatalogProductPrice>
                 */
                private array $productPrices = [];

                /**
                 * @var list<CatalogProductDeduction>|null
                 */
                private ?array $deductResult = null;

                /**
                 * @var list<array{productId: int, requestedQuantity: int}>
                 */
                private array $stockRequests = [];

                /**
                 * @var list<int>
                 */
                private array $priceRequests = [];

                /**
                 * @var list<array{operationId: string, items: list<array{productId: int, quantity: int, storeId?: int|null}>}>
                 */
                private array $deductRequests = [];

                public function __construct()
                {
                }

                public function setAvailableQuantity(int $availableQuantity): void
                {
                    $this->availableQuantity = $availableQuantity;
                }

                public function setProductFound(bool $productFound): void
                {
                    $this->productFound = $productFound;
                }

                public function setFail(bool $fail): void
                {
                    $this->fail = $fail;
                }

                /**
                 * @param array<int, CatalogProductPrice> $productPrices
                 */
                public function setProductPrices(array $productPrices): void
                {
                    $this->productPrices = $productPrices;
                }

                public function setDeductFailure(?\Throwable $deductFailure): void
                {
                    $this->deductFailure = $deductFailure;
                }

                /**
                 * @param list<CatalogProductDeduction>|null $deductResult
                 */
                public function setDeductResult(?array $deductResult): void
                {
                    $this->deductResult = $deductResult;
                }

                /**
                 * @return list<array{productId: int, requestedQuantity: int}>
                 */
                public function stockRequests(): array
                {
                    return $this->stockRequests;
                }

                /**
                 * @return list<int>
                 */
                public function priceRequests(): array
                {
                    return $this->priceRequests;
                }

                /**
                 * @return list<array{operationId: string, items: list<array{productId: int, quantity: int, storeId?: int|null}>}>
                 */
                public function deductRequests(): array
                {
                    return $this->deductRequests;
                }

                public function checkStock(int $productId, int $requestedQuantity): CatalogStockResponse
                {
                    $this->stockRequests[] = [
                        "productId" => $productId,
                        "requestedQuantity" => $requestedQuantity,
                    ];

                    if ($this->fail) {
                        throw new \RuntimeException("gRPC transport failed");
                    }

                    if (!$this->productFound) {
                        return new CatalogStockResponse($productId, 0, false, []);
                    }

                    return new CatalogStockResponse(
                        $productId,
                        $this->availableQuantity,
                        $requestedQuantity <= $this->availableQuantity,
                        [new CatalogStoreStock(1, $this->availableQuantity)],
                    );
                }

                /**
                 * @param int[] $productIds
                 *
                 * @return list<CatalogProductPrice>
                 */
                public function getProductPrices(array $productIds): array
                {
                    foreach ($productIds as $productId) {
                        $this->priceRequests[] = (int) $productId;
                    }

                    if ($this->fail) {
                        throw new InventoryServiceUnavailableException("Catalog inventory service is unavailable.");
                    }

                    $prices = [];
                    foreach ($productIds as $productId) {
                        $productId = (int) $productId;
                        $prices[] = $this->productPrices[$productId] ?? new CatalogProductPrice($productId, 1000, 100, 900);
                    }

                    return $prices;
                }

                /**
                 * @param list<array{productId: int, quantity: int, storeId?: int|null}> $items
                 */
                public function deductStocks(string $operationId, array $items): CatalogDeductStocksResult
                {
                    $this->deductRequests[] = [
                        "operationId" => $operationId,
                        "items" => $items,
                    ];

                    if ($this->deductFailure !== null) {
                        throw $this->deductFailure;
                    }

                    if (!$this->productFound) {
                        throw new InventoryItemNotFoundException("Product was not found.");
                    }

                    foreach ($items as $item) {
                        if ($item["quantity"] > $this->availableQuantity) {
                            throw new InsufficientStockException("Requested quantity is unavailable.");
                        }
                    }

                    if ($this->deductResult !== null) {
                        return new CatalogDeductStocksResult($operationId, $this->deductResult);
                    }

                    $deductions = [];
                    foreach ($items as $item) {
                        $deductions[] = new CatalogProductDeduction(
                            $item["productId"],
                            $item["quantity"],
                            5000 + $item["productId"],
                            [new CatalogStoreDeduction(1, $item["quantity"])],
                        );
                    }

                    return new CatalogDeductStocksResult($operationId, $deductions);
                }
            };

            static::getContainer()->set(CatalogInventoryClient::class, $this->catalogInventoryClient);
        }

        return $this->catalogInventoryClient;
    }

    private function messageBus(): TraceableMessageBus
    {
        if (!isset($this->messageBus)) {
            $this->messageBus = new TraceableMessageBus($this->recordingMessageBus());
        }

        return $this->messageBus;
    }

    protected function createCart(int $ownerId, int $itemCount = 2): Cart
    {
        $now = new \DateTimeImmutable("2026-01-01 10:00:00");
        $cart = (new Cart())
            ->setOwnerId($ownerId)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        for ($i = 1; $i <= $itemCount; $i++) {
            $cart->addItem(
                (new CartItem())
                    ->setCatalogElementId(1000 + $ownerId + $i)
                    ->setQuantity($i)
                    ->setSort($i * 100)
                    ->setCreatedAt($now)
                    ->setUpdatedAt($now)
            );
        }

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }

    protected function createOrder(
        int $ownerId,
        \DateTimeImmutable $createdAt,
        int $itemCount = 2,
        OrderStatus $status = OrderStatus::Pending,
    ): Order {
        $order = (new Order())
            ->setOwnerId($ownerId)
            ->setStatus($status)
            ->setTotalPrice("20.00")
            ->setTotalDiscount("1.00")
            ->setFinalPrice("19.00")
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($createdAt);

        for ($i = 1; $i <= $itemCount; $i++) {
            $order->addItem(
                (new OrderItem())
                    ->setProductSnapshotId(2000 + $ownerId + $i)
                    ->setQuantity($i)
                    ->setSort($i * 100)
                    ->setUnitPrice("10.00")
                    ->setUnitDiscount("0.50")
                    ->setFinalUnitPrice("9.50")
                    ->setLineTotal(sprintf('%d.%02d', intdiv(950 * $i, 100), (950 * $i) % 100))
                    ->setCreatedAt($createdAt)
            );
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
    private function recordingMessageBus(): RecordingMessageBus
    {
        if (!isset($this->recordingMessageBus)) {
            $this->recordingMessageBus = new RecordingMessageBus();
        }

        return $this->recordingMessageBus;
    }
}

final class RecordingMessageBus implements MessageBusInterface
{
    /**
     *  list<object>
     */
    private array $messages = [];

    /**
     *  array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }

    /**
     *  list<object>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
