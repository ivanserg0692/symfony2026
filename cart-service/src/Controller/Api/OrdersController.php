<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Order\OrderApiService;
use App\Security\CurrentUserProvider;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/orders")]
#[OA\Tag(name: "Orders")]
class OrdersController extends AbstractController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    #[Route("", name: "api_orders_list", methods: ["GET"])]
    #[OA\Get(
        summary: "List orders",
        description: "Returns the current user's orders with pagination, sorted by createdAt descending and id descending.",
        parameters: [
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1, default: 1)),
            new OA\Parameter(name: "limit", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1, maximum: self::MAX_LIMIT, default: self::DEFAULT_LIMIT)),
        ],
        security: [["XUserId" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated orders.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "items", type: "array", items: new OA\Items(ref: new Model(type: Order::class, groups: ["order:list"]))),
                        new OA\Property(
                            property: "pagination",
                            properties: [
                                new OA\Property(property: "page", type: "integer"),
                                new OA\Property(property: "limit", type: "integer"),
                                new OA\Property(property: "total", type: "integer"),
                                new OA\Property(property: "pages", type: "integer"),
                            ],
                            type: "object"
                        ),
                    ],
                    type: "object"
                )
            ),
            new OA\Response(response: 400, description: "X-User-Id header is missing or invalid."),
        ]
    )]
    public function list(Request $request, CurrentUserProvider $currentUserProvider, OrderApiService $orderApiService): JsonResponse
    {
        $ownerId = $currentUserProvider->getRequiredUserId($request);
        $page = max(1, $request->query->getInt("page", 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt("limit", self::DEFAULT_LIMIT)));

        return $this->json(
            $orderApiService->listOrders($ownerId, $page, $limit),
            context: ["groups" => ["order:list"]]
        );
    }

    #[Route("/{orderId<\d+>}", name: "api_orders_item", methods: ["GET"])]
    #[OA\Get(
        summary: "Get order",
        description: "Returns one current-user order with local order item data only.",
        parameters: [
            new OA\Parameter(name: "orderId", in: "path", required: true, schema: new OA\Schema(type: "integer", minimum: 1)),
        ],
        security: [["XUserId" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Order with items.",
                content: new OA\JsonContent(ref: new Model(type: Order::class, groups: ["order:item"]))
            ),
            new OA\Response(response: 400, description: "X-User-Id header is missing or invalid."),
            new OA\Response(response: 404, description: "Order was not found."),
        ]
    )]
    public function item(int $orderId, Request $request, CurrentUserProvider $currentUserProvider, OrderApiService $orderApiService): JsonResponse
    {
        $ownerId = $currentUserProvider->getRequiredUserId($request);
        $order = $orderApiService->findOrder($orderId, $ownerId);

        if ($order === null) {
            return $this->json(["message" => "Order was not found."], Response::HTTP_NOT_FOUND);
        }

        return $this->json($order, context: ["groups" => ["order:item"]]);
    }
}
