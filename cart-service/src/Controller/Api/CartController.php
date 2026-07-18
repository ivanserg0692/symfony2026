<?php

namespace App\Controller\Api;

use App\Cart\CartApiService;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Security\CurrentUserProvider;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/cart")]
#[OA\Tag(name: "Cart")]
class CartController extends AbstractController
{
    #[Route("", name: "api_cart_get", methods: ["GET"])]
    #[OA\Get(
        summary: "Get current cart",
        description: "Returns the current user's active cart with local cart item data only.",
        security: [["XUserId" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Current cart or an empty cart representation.",
                content: new OA\JsonContent(ref: new Model(type: Cart::class, groups: ["cart:item"]))
            ),
            new OA\Response(response: 400, description: "X-User-Id header is missing or invalid."),
        ]
    )]
    public function getCart(Request $request, CurrentUserProvider $currentUserProvider, CartApiService $cartApiService): JsonResponse
    {
        $ownerId = $currentUserProvider->getRequiredUserId($request);
        $cart = $cartApiService->findCurrentCart($ownerId);

        if ($cart === null) {
            return $this->json($this->emptyCart());
        }

        return $this->json($cart, context: ["groups" => ["cart:item"]]);
    }

    #[Route("/items/{itemId<\d+>}", name: "api_cart_item_patch", methods: ["PATCH"])]
    #[OA\Patch(
        summary: "Update cart item",
        description: "Updates quantity and sort for an item that belongs to the current user's active cart.",
        parameters: [
            new OA\Parameter(name: "itemId", in: "path", required: true, schema: new OA\Schema(type: "integer", minimum: 1)),
        ],
        security: [["XUserId" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "quantity", type: "integer", minimum: 1),
                    new OA\Property(property: "sort", type: "integer"),
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Updated cart item.",
                content: new OA\JsonContent(ref: new Model(type: CartItem::class, groups: ["cart:item"]))
            ),
            new OA\Response(response: 400, description: "Invalid request body or X-User-Id header."),
            new OA\Response(response: 404, description: "Cart item was not found."),
        ]
    )]
    public function updateItem(int $itemId, Request $request, CurrentUserProvider $currentUserProvider, CartApiService $cartApiService): JsonResponse
    {
        $ownerId = $currentUserProvider->getRequiredUserId($request);

        try {
            $payload = $this->decodeJsonObject($request);
            $item = $cartApiService->updateItem($itemId, $ownerId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(["message" => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if ($item === null) {
            return $this->json(["message" => "Cart item was not found."], Response::HTTP_NOT_FOUND);
        }

        return $this->json($item, context: ["groups" => ["cart:item"]]);
    }

    #[Route("/items/{itemId<\d+>}", name: "api_cart_item_delete", methods: ["DELETE"])]
    #[OA\Delete(
        summary: "Delete cart item",
        description: "Deletes an item from the current user's active cart.",
        parameters: [
            new OA\Parameter(name: "itemId", in: "path", required: true, schema: new OA\Schema(type: "integer", minimum: 1)),
        ],
        security: [["XUserId" => []]],
        responses: [
            new OA\Response(response: 204, description: "Cart item was deleted."),
            new OA\Response(response: 400, description: "X-User-Id header is missing or invalid."),
            new OA\Response(response: 404, description: "Cart item was not found."),
        ]
    )]
    public function deleteItem(int $itemId, Request $request, CurrentUserProvider $currentUserProvider, CartApiService $cartApiService): Response
    {
        $ownerId = $currentUserProvider->getRequiredUserId($request);

        if (!$cartApiService->deleteItem($itemId, $ownerId)) {
            return $this->json(["message" => "Cart item was not found."], Response::HTTP_NOT_FOUND);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route("", name: "api_cart_delete", methods: ["DELETE"])]
    #[OA\Delete(
        summary: "Clear current cart",
        description: "Deletes the current user's active cart and its items. The operation is idempotent.",
        security: [["XUserId" => []]],
        responses: [
            new OA\Response(response: 204, description: "Cart was deleted or was already absent."),
            new OA\Response(response: 400, description: "X-User-Id header is missing or invalid."),
        ]
    )]
    public function clearCart(Request $request, CurrentUserProvider $currentUserProvider, CartApiService $cartApiService): Response
    {
        $ownerId = $currentUserProvider->getRequiredUserId($request);
        $cartApiService->clearCart($ownerId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{id: null, status: null, createdAt: null, updatedAt: null, items: array{}}
     */
    private function emptyCart(): array
    {
        return [
            "id" => null,
            "status" => null,
            "createdAt" => null,
            "updatedAt" => null,
            "items" => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            throw new \InvalidArgumentException("Request body must contain valid JSON.");
        }
    }
}
