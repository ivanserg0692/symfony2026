<?php

namespace App\Tests\Functional;

use App\Order\OrderApiService;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\Response;

class OrdersApiTest extends ApiTestCase
{
    public function testListReturnsOnlyOwnOrders(): void
    {
        $ownOrder = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));
        $this->createOrder(456, new \DateTimeImmutable("2026-01-03 10:00:00"));

        $this->requestJson("GET", "/api/orders", 123);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse();
        self::assertSame(1, $payload["pagination"]["total"]);
        self::assertSame($ownOrder->getId(), $payload["items"][0]["id"]);
        self::assertArrayNotHasKey("items", $payload["items"][0]);
        self::assertArrayNotHasKey("ownerId", $payload["items"][0]);
    }

    public function testPagination(): void
    {
        $this->createOrder(123, new \DateTimeImmutable("2026-01-01 10:00:00"));
        $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));
        $this->createOrder(123, new \DateTimeImmutable("2026-01-03 10:00:00"));

        $this->requestJson("GET", "/api/orders?page=2&limit=2", 123);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse();
        self::assertCount(1, $payload["items"]);
        self::assertSame(2, $payload["pagination"]["page"]);
        self::assertSame(2, $payload["pagination"]["limit"]);
        self::assertSame(3, $payload["pagination"]["total"]);
        self::assertSame(2, $payload["pagination"]["pages"]);
    }

    public function testStableSortingByCreatedAtAndIdDescending(): void
    {
        $older = $this->createOrder(123, new \DateTimeImmutable("2026-01-01 10:00:00"));
        $sameDateLowerId = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));
        $sameDateHigherId = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));

        $this->requestJson("GET", "/api/orders", 123);

        self::assertResponseIsSuccessful();
        $ids = array_column($this->jsonResponse()["items"], "id");
        self::assertSame([$sameDateHigherId->getId(), $sameDateLowerId->getId(), $older->getId()], $ids);
    }

    public function testGetOwnOrderWithItems(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));

        $this->requestJson("GET", sprintf("/api/orders/%d", $order->getId()), 123);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse();
        self::assertSame($order->getId(), $payload["id"]);
        self::assertSame("20.00", $payload["totalPrice"]);
        self::assertCount(2, $payload["items"]);
        self::assertArrayHasKey("productSnapshotId", $payload["items"][0]);
        self::assertArrayHasKey("lineTotal", $payload["items"][0]);
        self::assertArrayNotHasKey("ownerId", $payload);
    }

    public function testForeignOrderReturnsNotFound(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));

        $this->requestJson("GET", sprintf("/api/orders/%d", $order->getId()), 456);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMissingUserHeaderReturnsBadRequest(): void
    {
        $this->requestJson("GET", "/api/orders");

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testInvalidUserHeaderReturnsBadRequest(): void
    {
        $this->client->request("GET", "/api/orders", server: ["HTTP_X_USER_ID" => "0"]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testOrderListDoesNotInitializeItemsCollections(): void
    {
        $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));
        $this->entityManager->clear();

        $result = static::getContainer()->get(OrderApiService::class)->listOrders(123, 1, 20);
        $order = $result["items"][0];
        $items = $order->getItems();

        self::assertInstanceOf(PersistentCollection::class, $items);
        self::assertFalse($items->isInitialized());
    }
}
