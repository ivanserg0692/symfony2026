<?php

namespace App\Tests\Functional;

use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\HttpFoundation\Response;

class CartApiTest extends ApiTestCase
{
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

    public function testUpdateQuantity(): void
    {
        $item = $this->firstItem($this->createCart(123));

        $this->requestJson("PATCH", sprintf("/api/cart/items/%d", $item->getId()), 123, ["quantity" => 7]);

        self::assertResponseIsSuccessful();
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
