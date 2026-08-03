<?php

namespace App\Tests\Functional;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Grpc\CatalogProductDeduction;
use App\Grpc\InventoryServiceUnavailableException;
use App\Order\OrderApiService;
use App\Order\OrderCanceledMessage;
use App\Order\OrderStatus;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\Response;

class OrdersApiTest extends ApiTestCase
{
    public function testCreateOrderFromActiveCart(): void
    {
        $cart = $this->createCart(123);

        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = $this->jsonResponse();
        self::assertSame("pending", $payload["status"]);
        self::assertSame("30.00", $payload["totalPrice"]);
        self::assertSame("3.00", $payload["totalDiscount"]);
        self::assertSame("27.00", $payload["finalPrice"]);
        self::assertCount(2, $payload["items"]);
        self::assertSame(6124, $payload["items"][0]["productSnapshotId"]);
        self::assertSame(1, $payload["items"][0]["quantity"]);
        self::assertSame("9.00", $payload["items"][0]["finalUnitPrice"]);
        self::assertSame("9.00", $payload["items"][0]["lineTotal"]);
        self::assertSame(6125, $payload["items"][1]["productSnapshotId"]);
        self::assertSame("18.00", $payload["items"][1]["lineTotal"]);
        self::assertSame(1, $this->entityManager->getRepository(Order::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(OrderItem::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(Cart::class)->count([]));
        self::assertSame([$cart->getItems()[0]->getCatalogElementId(), $cart->getItems()[1]->getCatalogElementId()], $this->catalogPriceRequests());
        self::assertStringStartsWith("cart-checkout-", $this->catalogDeductRequests()[0]["operationId"]);
        self::assertMatchesRegularExpression('/^cart-checkout-[a-f0-9]{32}$/', $this->catalogDeductRequests()[0]["operationId"]);

        $this->requestJson("GET", "/api/cart", 123);
        self::assertResponseIsSuccessful();
        self::assertNull($this->jsonResponse()["id"]);
    }

    public function testCreateOrderReturnsNotFoundWhenActiveCartIsAbsent(): void
    {
        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(0, $this->entityManager->getRepository(Order::class)->count([]));
        self::assertSame([], $this->catalogDeductRequests());
    }

    public function testCreateOrderReturnsConflictWhenCartIsEmpty(): void
    {
        $this->createCart(123, 0);

        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(0, $this->entityManager->getRepository(Order::class)->count([]));
        self::assertSame([], $this->catalogDeductRequests());
    }

    public function testCreateOrderReturnsConflictWhenStockIsInsufficient(): void
    {
        $this->createCart(123);
        $this->setCatalogStockAvailableQuantity(0);

        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(0, $this->entityManager->getRepository(Order::class)->count([]));
    }

    public function testCreateOrderReturnsServiceUnavailableWhenGrpcFails(): void
    {
        $this->createCart(123);
        $this->setCatalogDeductFailure(new InventoryServiceUnavailableException("Catalog inventory service is unavailable."));

        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertSame(0, $this->entityManager->getRepository(Order::class)->count([]));
    }

    public function testCreateOrderRejectsMissingSnapshotId(): void
    {
        $cart = $this->createCart(123);
        $this->setCatalogDeductResult([
            new CatalogProductDeduction((int) $cart->getItems()[0]->getCatalogElementId(), 1, 0, []),
            new CatalogProductDeduction((int) $cart->getItems()[1]->getCatalogElementId(), 2, 6125, []),
        ]);

        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_GATEWAY);
        self::assertSame(0, $this->entityManager->getRepository(Order::class)->count([]));
    }

    public function testCreateOrderRejectsIncompleteDeductStocksResponse(): void
    {
        $cart = $this->createCart(123);
        $this->setCatalogDeductResult([
            new CatalogProductDeduction((int) $cart->getItems()[0]->getCatalogElementId(), 1, 6124, []),
        ]);

        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_GATEWAY);
        self::assertSame(0, $this->entityManager->getRepository(Order::class)->count([]));
    }

    public function testRepeatedCreateOrderDoesNotCreateSecondOrder(): void
    {
        $this->createCart(123);

        $this->requestJson("POST", "/api/orders", 123);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->requestJson("POST", "/api/orders", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(1, $this->entityManager->getRepository(Order::class)->count([]));
        self::assertCount(1, $this->catalogDeductRequests());
    }

    public function testCancelOrder(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));

        $this->requestJson("POST", sprintf("/api/orders/%d/cancel", $order->getId()), 123);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse();
        self::assertSame("canceled", $payload["status"]);
        self::assertSame(2, $this->entityManager->getRepository(OrderItem::class)->count([]));
        self::assertCount(1, $this->dispatchedMessages());
        self::assertInstanceOf(OrderCanceledMessage::class, $this->dispatchedMessages()[0]);
        self::assertSame($order->getId(), $this->dispatchedMessages()[0]->orderId);
    }

    public function testRepeatedCancelDoesNotDispatchMessageAgain(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));

        $this->requestJson("POST", sprintf("/api/orders/%d/cancel", $order->getId()), 123);
        self::assertResponseIsSuccessful();

        $this->requestJson("POST", sprintf("/api/orders/%d/cancel", $order->getId()), 123);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertCount(1, $this->dispatchedMessages());
    }

    public function testForeignCancelReturnsNotFound(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));

        $this->requestJson("POST", sprintf("/api/orders/%d/cancel", $order->getId()), 456);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame([], $this->dispatchedMessages());
    }

    public function testMissingCancelReturnsNotFound(): void
    {
        $this->requestJson("POST", "/api/orders/999/cancel", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame([], $this->dispatchedMessages());
    }

    public function testCompletedOrderCannotBeCanceled(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"), status: OrderStatus::Completed);

        $this->requestJson("POST", sprintf("/api/orders/%d/cancel", $order->getId()), 123);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame([], $this->dispatchedMessages());
    }

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
        self::assertSame("pending", $payload["status"]);
        self::assertCount(2, $payload["items"]);
        self::assertArrayHasKey("productSnapshotId", $payload["items"][0]);
        self::assertArrayHasKey("lineTotal", $payload["items"][0]);
        self::assertSame("Snapshot product " . $payload["items"][0]["productSnapshotId"], $payload["items"][0]["productSnapshot"]["product"]["name"]);
        self::assertSame($payload["items"][0]["productSnapshotId"], $payload["items"][0]["productSnapshot"]["id"]);
        self::assertArrayNotHasKey("ownerId", $payload);
        self::assertSame([[2124, 2125]], $this->catalogSnapshotRequests());
    }

    public function testForeignOrderDoesNotRequestSnapshots(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));

        $this->requestJson("GET", sprintf("/api/orders/%d", $order->getId()), 456);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame([], $this->catalogSnapshotRequests());
    }

    public function testOrderDetailDeduplicatesSnapshotIds(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));
        $items = $order->getItems()->toArray();
        $items[1]->setProductSnapshotId((int) $items[0]->getProductSnapshotId());
        $this->entityManager->flush();

        $this->requestJson("GET", sprintf("/api/orders/%d", $order->getId()), 123);

        self::assertResponseIsSuccessful();
        self::assertSame([[2124]], $this->catalogSnapshotRequests());
    }

    public function testOrderDetailReturnsBadGatewayWhenSnapshotResponseIsMissingItem(): void
    {
        $order = $this->createOrder(123, new \DateTimeImmutable("2026-01-02 10:00:00"));
        $this->setCatalogSnapshotResult([]);

        $this->requestJson("GET", sprintf("/api/orders/%d", $order->getId()), 123);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_GATEWAY);
        self::assertSame([[2124, 2125]], $this->catalogSnapshotRequests());
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
