<?php

namespace App\Controller\Api;

use App\Entity\CatalogElements;
use App\Repository\CatalogElementsRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/catalog/elements")]
#[OA\Tag(name: "Catalog Elements")]
class CatalogElementsController extends AbstractController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    #[Route("", name: "api_catalog_elements_list", methods: ["GET"])]
    #[OA\Get(
        summary: "List catalog elements",
        description: "Returns catalog elements filtered by sectionId and active with pagination.",
        parameters: [
            new OA\Parameter(name: "sectionId", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1)),
            new OA\Parameter(name: "active", in: "query", required: false, schema: new OA\Schema(type: "boolean")),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1, default: 1)),
            new OA\Parameter(name: "limit", in: "query", required: false, schema: new OA\Schema(type: "integer", minimum: 1, maximum: self::MAX_LIMIT, default: self::DEFAULT_LIMIT)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated catalog elements.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "items", type: "array", items: new OA\Items(ref: new Model(type: CatalogElements::class, groups: ["catalog_element:list"]))),
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
    public function list(Request $request, CatalogElementsRepository $catalogElementsRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt("page", 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt("limit", self::DEFAULT_LIMIT)));
        $sectionId = $request->query->has("sectionId") ? max(1, $request->query->getInt("sectionId")) : null;
        $active = $this->getNullableBooleanQuery($request, "active");

        $ids = $catalogElementsRepository->findPageIds($sectionId, $active, $page, $limit);
        $items = $catalogElementsRepository->findListByIds($ids);
        $total = $catalogElementsRepository->countMatchingListFilters($sectionId, $active);

        return $this->json(
            [
                "items" => $items,
                "pagination" => [
                    "page" => $page,
                    "limit" => $limit,
                    "total" => $total,
                ],
            ],
            context: ["groups" => ["catalog_element:list"]]
        );
    }

    #[Route("/{id<\d+>}", name: "api_catalog_elements_item", methods: ["GET"])]
    #[OA\Get(
        summary: "Get catalog element",
        description: "Returns complete product information with sections, prices and total stock.",
        responses: [
            new OA\Response(
                response: 200,
                description: "Catalog element.",
                content: new OA\JsonContent(ref: new Model(type: CatalogElements::class, groups: ["catalog_element:item"]))
            ),
            new OA\Response(response: 404, description: "Catalog element was not found."),
        ]
    )]
    public function item(int $id, CatalogElementsRepository $catalogElementsRepository): JsonResponse
    {
        $element = $catalogElementsRepository->findOneForPublicApi($id);

        if ($element === null) {
            return $this->json(["message" => "Catalog element was not found."], Response::HTTP_NOT_FOUND);
        }

        return $this->json($element, context: ["groups" => ["catalog_element:item"]]);
    }

    private function getNullableBooleanQuery(Request $request, string $name): ?bool
    {
        if (!$request->query->has($name)) {
            return null;
        }

        $value = filter_var($request->query->get($name), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($value) ? $value : null;
    }
}
