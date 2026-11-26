<?php

namespace App\Tests\Functional;

use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\HttpFoundation\Response;

class CartApiTest extends ApiTestCase
{
    public function testMergeReplaysOriginalResultAndTransfersOnlyAvailableLines(): void
    {
        $this->createCart(123);
        $this->setCatalogStockAvailableQuantity(4);
        $request = [
            'operationId' => '7ebc6ad6-70ac-463a-bb1e-d5f5b97e98b3',
            'mode' => 'merge',
            'items' => [
                ['productId' => 1124, 'quantity' => 2],
                ['productId' => 1125, 'quantity' => 3],
            ],
        ];

        $this->requestJson('POST', '/api/cart/transfer', 123, $request);
        self::assertResponseIsSuccessful();
        self::assertSame(['transferred' => [1124], 'unavailable' => [1125]], $this->jsonResponse());

        $this->requestJson('POST', '/api/cart/transfer', 123, $request);
        self::assertResponseIsSuccessful();
        self::assertSame(['transferred' => [1124], 'unavailable' => [1125]], $this->jsonResponse());
        self::assertCount(2, $this->catalogStockRequests());

        $this->requestJson('GET', '/api/cart', 123);
        self::assertSame([1124 => 3, 1125 => 2], array_column($this->jsonResponse()['items'], 'quantity', 'catalogElementId'));

        $request['mode'] = 'replace';
        $this->requestJson('POST', '/api/cart/transfer', 123, $request);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testReplacePreservesExistingCartUntilAllGuestLinesAreAvailable(): void
    {
        $this->createCart(123);
        $this->setCatalogStockAvailableQuantity(4);
        $request = [
            'operationId' => '7ebc6ad6-70ac-463a-bb1e-d5f5b97e98b3',
            'mode' => 'replace',
            'items' => [
                ['productId' => 1124, 'quantity' => 3],
                ['productId' => 3001, 'quantity' => 5],
            ],
        ];

        $this->requestJson('POST', '/api/cart/transfer', 123, $request);
        self::assertResponseIsSuccessful();
        self::assertSame(['transferred' => [], 'unavailable' => [3001]], $this->jsonResponse());

        $this->requestJson('GET', '/api/cart', 123);
        self::assertSame([1124 => 1, 1125 => 2], array_column($this->jsonResponse()['items'], 'quantity', 'catalogElementId'));

        $this->setCatalogStockAvailableQuantity(5);
        $request['operationId'] = 'd761d868-a2f4-4787-abf1-fe1df175d41c';
        $this->requestJson('POST', '/api/cart/transfer', 123, $request);
        self::assertResponseIsSuccessful();
        self::assertSame(['transferred' => [1124, 3001], 'unavailable' => []], $this->jsonResponse());

        $this->entityManager->clear();
        $this->requestJson('GET', '/api/cart', 123);
        self::assertSame([1124 => 3, 3001 => 5], array_column($this->jsonResponse()['items'], 'quantity', 'catalogElementId'));
    }

    public function testTransferRequiresAuthenticationAndRejectsOwnerInBody(): void
    {
        $request = [
            'operationId' => '7ebc6ad6-70ac-463a-bb1e-d5f5b97e98b3',
            'mode' => 'merge',
            'items' => [['productId' => 501, 'quantity' => 0]],
        ];

        $this->requestJson('POST', '/api/cart/transfer', 123, $request);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $request['items'][0]['quantity'] = 1;
        $request['items'][] = $request['items'][0];
        $this->requestJson('POST', '/api/cart/transfer', 123, $request);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        array_pop($request['items']);
        $this->requestJson('POST', '/api/cart/transfer', body: $request);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('X-User-Id header must be a positive integer.', $this->jsonResponse()['message']);

        $this->requestJson('POST', '/api/cart/transfer', 456, $request + ['ownerId' => 123]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertCount(0, $this->catalogStockRequests());
    }

    public function testGetExistingCart(): void
    {
        $cart = $this->createCart(123);

        $this->requestJson("GET", "/api/cart", 123);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse();
        self::assertSame($cart->getId(), $payload["id"]);
        self::assertSame("active", $payload["status"]);
        self::assertCount(2, $payload["items"]);
        self::assertArrayHasKey("catalogElementId", $payload["items"][0]);
        self::assertArrayNotHasKey("ownerId", $payload);
    }

    public function testGetEmptyCartWhenAbsent(): void
    {
        $this->requestJson("GET", "/api/cart", 123);

        self::assertResponseIsSuccessful();
        self::assertSame([
            "id" => null,
            "status" => null,
            "createdAt" => null,
            "updatedAt" => null,
            "items" => [],
        ], $this->jsonResponse());
    }

    public function testCartIsFilteredByHeaderUser(): void
    {
        $this->createCart(123);

        $this->requestJson("GET", "/api/cart", 456);

        self::assertResponseIsSuccessful();
        self::assertNull($this->jsonResponse()["id"]);
    }

    public function testAddItemCreatesFirstItemAndCart(): void
    {
        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => 501, "quantity" => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = $this->jsonResponse();
        self::assertSame(501, $payload["catalogElementId"]);
        self::assertSame(2, $payload["quantity"]);

        self::assertSame(1, $this->entityManager->getRepository(Cart::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(CartItem::class)->count([]));
    }

    public function testAddItemToExistingCart(): void
    {
        $this->createCart(123);

        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => 999, "quantity" => 4]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = $this->jsonResponse();
        self::assertSame(999, $payload["catalogElementId"]);
        self::assertSame(4, $payload["quantity"]);
        self::assertSame(3, $this->entityManager->getRepository(CartItem::class)->count([]));
    }

    public function testAddItemIncreasesExistingQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => $item->getCatalogElementId(), "quantity" => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(3, $this->jsonResponse()["quantity"]);
        self::assertSame(2, $this->entityManager->getRepository(CartItem::class)->count([]));
    }

    public function testAddItemChecksResultingQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => $item->getCatalogElementId(), "quantity" => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([
            ["productId" => $item->getCatalogElementId(), "requestedQuantity" => 3],
        ], $this->catalogStockRequests());
    }

    public function testAddItemChecksStockInsideTransaction(): void
    {
        $item = $this->firstItem($this->createCart(123));
        $checkedInsideTransaction = false;

        $this->setCatalogCheckStockCallback(function () use (&$checkedInsideTransaction): void {
            $checkedInsideTransaction = $this->entityManager->getConnection()->isTransactionActive();
        });

        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => $item->getCatalogElementId(), "quantity" => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertTrue($checkedInsideTransaction);
        self::assertSame(3, $this->jsonResponse()["quantity"]);
    }

    public function testAddItemReturnsNotFoundWhenProductIsMissing(): void
    {
        $this->setCatalogProductFound(false);

        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => 501, "quantity" => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame("Product was not found.", $this->jsonResponse()["message"]);
        self::assertSame(0, $this->entityManager->getRepository(Cart::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(CartItem::class)->count([]));
    }

    public function testAddItemReturnsConflictWhenQuantityIsUnavailable(): void
    {
        $this->setCatalogStockAvailableQuantity(3);

        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => 501, "quantity" => 5]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame([
            "message" => "Requested quantity is unavailable",
            "productId" => 501,
            "requestedQuantity" => 5,
            "availableQuantity" => 3,
        ], $this->jsonResponse());
        self::assertSame(0, $this->entityManager->getRepository(Cart::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(CartItem::class)->count([]));
    }

    public function testAddItemRejectsInvalidProductId(): void
    {
        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => 0, "quantity" => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testAddItemRejectsInvalidQuantity(): void
    {
        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => 501, "quantity" => 0]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testAddItemRejectsExtraFields(): void
    {
        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => 501, "quantity" => 2, "ownerId" => 123]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testAddItemDoesNotChangeCartWhenGrpcFails(): void
    {
        $cart = $this->createCart(123, 1);
        $item = $this->firstItem($cart);
        $this->setCatalogInventoryFailure(true);

        $this->requestJson("POST", "/api/cart/items", 123, ["productId" => $item->getCatalogElementId(), "quantity" => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $this->entityManager->clear();
        $persistedItem = $this->entityManager->find(CartItem::class, $item->getId());
        self::assertInstanceOf(CartItem::class, $persistedItem);
        self::assertSame(1, $persistedItem->getQuantity());
        self::assertSame(1, $this->entityManager->getRepository(CartItem::class)->count([]));
    }

    public function testUpdateQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["quantity" => 7]);

        self::assertResponseIsSuccessful();
        self::assertSame(7, $this->jsonResponse()["quantity"]);
    }

    public function testUpdateItemChecksStockInsideTransaction(): void
    {
        $item = $this->firstItem($this->createCart(123));
        $checkedInsideTransaction = false;

        $this->setCatalogCheckStockCallback(function () use (&$checkedInsideTransaction): void {
            $checkedInsideTransaction = $this->entityManager->getConnection()->isTransactionActive();
        });

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["quantity" => 7]);

        self::assertResponseIsSuccessful();
        self::assertTrue($checkedInsideTransaction);
        self::assertSame(7, $this->jsonResponse()["quantity"]);
    }

    public function testRejectsUnavailableQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));
        $this->setCatalogStockAvailable(false);

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["quantity" => 7]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame("Requested quantity exceeds available stock.", $this->jsonResponse()["message"]);

        $this->entityManager->clear();
        $persistedItem = $this->entityManager->find(CartItem::class, $item->getId());
        self::assertInstanceOf(CartItem::class, $persistedItem);
        self::assertSame(1, $persistedItem->getQuantity());
    }

    public function testUpdateSort(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["sort" => 999]);

        self::assertResponseIsSuccessful();
        self::assertSame(999, $this->jsonResponse()["sort"]);
    }

    public function testRejectsNonPositiveQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["quantity" => 0]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsEmptyUpdatePayload(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsNullQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["quantity" => null]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsNonIntegerQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["quantity" => "7"]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsNonIntegerSort(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["sort" => "999"]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsCatalogElementChange(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["catalogElementId" => 999]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCannotUpdateForeignItem(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 456, ["quantity" => 7]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteOwnItem(): void
    {
        $item = $this->firstItem($this->createCart(123));
        $itemId = $item->getId();

        $this->requestJson("DELETE", sprintf("/api/cart/items/%d", $itemId), 123);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->entityManager->find(CartItem::class, $itemId));
    }

    public function testCannotDeleteForeignItem(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("DELETE", sprintf("/api/cart/items/%d", $item->getId()), 456);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertNotNull($this->entityManager->find(CartItem::class, $item->getId()));
    }

    public function testClearCartDeletesCartAndItems(): void
    {
        $cart = $this->createCart(123);
        $cartId = $cart->getId();
        $itemId = $this->firstItem($cart)->getId();

        $this->requestJson("DELETE", "/api/cart", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->entityManager->find(Cart::class, $cartId));
        self::assertNull($this->entityManager->find(CartItem::class, $itemId));
    }

    public function testClearAbsentCartIsIdempotent(): void
    {
        $this->requestJson("DELETE", "/api/cart", 123);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testMissingUserHeaderReturnsBadRequest(): void
    {
        $this->requestJson("GET", "/api/cart");

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testInvalidUserHeaderReturnsBadRequest(): void
    {
        $this->client->request("GET", "/api/cart", server: ["HTTP_X_USER_ID" => "abc"]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testOpenApiJsonAllowsSwaggerUiCorsPreflight(): void
    {
        $this->client->request("OPTIONS", "/api/doc.json", server: ["HTTP_ORIGIN" => "http://localhost:8000", "HTTP_ACCESS_CONTROL_REQUEST_METHOD" => "GET"]);

        self::assertResponseIsSuccessful();
        self::assertSame("http://localhost:8000", $this->client->getResponse()->headers->get("Access-Control-Allow-Origin"));
        self::assertSame("GET, OPTIONS", $this->client->getResponse()->headers->get("Access-Control-Allow-Methods"));
    }

    public function testCartApiAllowsSwaggerUiCorsPreflight(): void
    {
        $this->client->request("OPTIONS", "/api/cart", server: [
            "HTTP_ORIGIN" => "http://localhost:8000",
            "HTTP_ACCESS_CONTROL_REQUEST_METHOD" => "GET",
            "HTTP_ACCESS_CONTROL_REQUEST_HEADERS" => "X-User-Id",
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame("http://localhost:8000", $this->client->getResponse()->headers->get("Access-Control-Allow-Origin"));
        self::assertStringContainsString("GET", (string) $this->client->getResponse()->headers->get("Access-Control-Allow-Methods"));
        self::assertStringContainsString("x-user-id", strtolower((string) $this->client->getResponse()->headers->get("Access-Control-Allow-Headers")));
    }

    private function firstItem(Cart $cart): CartItem
    {
        $item = $cart->getItems()->first();
        self::assertInstanceOf(CartItem::class, $item);

        return $item;
    }
}
