<?php

namespace App\Controller\Api;

use App\Entity\ProductSnapshot;
use App\Repository\ProductSnapshotRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/product-snapshots")]
#[OA\Tag(name: "Product Snapshots")]
class ProductSnapshotsController extends AbstractController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const ALLOWED_SORTS = ["id", "createdAt"];

    #[Route("", name: "api_product_snapshots_list", methods: ["GET"])]
    #[OA\Get(
        summary: "List product snapshots",
        description: "Returns product snapshots with copied product data, optional original product filter and pagination.",
        parameters: [
            new OA\Parameter(name: "originalProductId", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1)),
            new OA\Parameter(name: "sort", in: "query", required: false, schema: new OA\Schema(type: "string", enum: self::ALLOWED_SORTS, default: "id")),
            new OA\Parameter(name: "direction", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["asc", "desc"], default: "asc")),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1, default: 1)),
            new OA\Parameter(name: "limit", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1, maximum: self::MAX_LIMIT, default: self::DEFAULT_LIMIT)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated product snapshots.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "items", type: "array", items: new OA\Items(ref: new Model(type: ProductSnapshot::class, groups: ["product_snapshot:list"]))),
                        new OA\Property(
                            property: "pagination",
                            properties: [
                                new OA\Property(property: "page", type: "integer"),
                                new OA\Property(property: "limit", type: "integer"),
                                new OA\Property(property: "total", type: "integer"),
                            ],
                            type: "object"
                        ),
                    ],
                    type: "object"
                )
            ),
        ]
    )]
    public function list(Request $request, ProductSnapshotRepository $productSnapshotRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt("page", 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt("limit", self::DEFAULT_LIMIT)));
        $originalProductId = $request->query->has("originalProductId") ? max(1, $request->query->getInt("originalProductId")) : null;
        $sort = $this->getSort($request);
        $direction = $this->getDirection($request);

        $ids = $productSnapshotRepository->findPageIds($originalProductId, $sort, $direction, $page, $limit);
        $items = $productSnapshotRepository->findListByIds($ids);
        $total = $productSnapshotRepository->countMatchingListFilters($originalProductId);

        return $this->json(
            [
                "items" => $items,
                "pagination" => [
                    "page" => $page,
                    "limit" => $limit,
                    "total" => $total,
                ],
            ],
            context: ["groups" => ["product_snapshot:list"]]
        );
    }

    #[Route("/{id<\d+>}", name: "api_product_snapshots_item", methods: ["GET"])]
    #[OA\Get(
        summary: "Get product snapshot",
        description: "Returns copied product data and the source catalog element for a product snapshot.",
        responses: [
            new OA\Response(
                response: 200,
                description: "Product snapshot.",
                content: new OA\JsonContent(ref: new Model(type: ProductSnapshot::class, groups: ["product_snapshot:item"]))
            ),
            new OA\Response(response: 404, description: "Product snapshot was not found."),
        ]
    )]
    public function item(int $id, ProductSnapshotRepository $productSnapshotRepository): JsonResponse
    {
        $snapshot = $productSnapshotRepository->findOneForPublicApi($id);

        if ($snapshot === null) {
            return $this->json(["message" => "Product snapshot was not found."], Response::HTTP_NOT_FOUND);
        }

        return $this->json($snapshot, context: ["groups" => ["product_snapshot:item"]]);
    }

    private function getSort(Request $request): string
    {
        $sort = $request->query->get("sort", "id");

        return \in_array($sort, self::ALLOWED_SORTS, true) ? $sort : "id";
    }

    private function getDirection(Request $request): string
    {
        $direction = strtolower((string) $request->query->get("direction", "asc"));

        return $direction === "desc" ? "desc" : "asc";
    }
}
