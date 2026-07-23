<?php

namespace App\Tests\Functional;

use App\Entity\Cart;
use App\Grpc\CatalogInventoryClient;
use App\Grpc\CatalogStockResponse;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Grpc\CatalogStoreStock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    private CatalogInventoryClient $catalogInventoryClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
        }

        $schemaTool->createSchema($metadata);

        $this->setCatalogStockAvailable(true);
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
     * @return list<array{productId: int, requestedQuantity: int}>
     */
    protected function catalogStockRequests(): array
    {
        return $this->catalogInventoryClient()->requests();
    }

    private function catalogInventoryClient(): CatalogInventoryClient
    {
        if (!isset($this->catalogInventoryClient)) {
            $this->catalogInventoryClient = new class extends CatalogInventoryClient {
                private int $availableQuantity = 1000000;
                private bool $productFound = true;
                private bool $fail = false;

                /**
                 * @var list<array{productId: int, requestedQuantity: int}>
                 */
                private array $requests = [];

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
                 * @return list<array{productId: int, requestedQuantity: int}>
                 */
                public function requests(): array
                {
                    return $this->requests;
                }

                public function checkStock(int $productId, int $requestedQuantity): CatalogStockResponse
                {
                    $this->requests[] = [
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
            };

            static::getContainer()->set(CatalogInventoryClient::class, $this->catalogInventoryClient);
        }

        return $this->catalogInventoryClient;
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

    protected function createOrder(int $ownerId, \DateTimeImmutable $createdAt, int $itemCount = 2): Order
    {
        $order = (new Order())
            ->setOwnerId($ownerId)
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
                    ->setLineTotal((string) (9.50 * $i))
                    ->setCreatedAt($createdAt)
            );
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}
