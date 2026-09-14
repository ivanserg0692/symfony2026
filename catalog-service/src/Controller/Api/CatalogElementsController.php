<?php

namespace App\Controller\Api;

use App\Search\Product\Application\CatalogReadService;
use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Application\Dto\Read\CatalogListQuery;
use App\Search\Product\Application\Dto\Read\CatalogListResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/catalog/elements")]
#[OA\Tag(name: "Catalog Elements")]
class CatalogElementsController extends AbstractController
{
    public function __construct(
        private readonly bool $useHasNextPagePagination,
    )
    {
    }

    #[Route("", name: "api_catalog_elements_list", methods: ["GET"])]
    #[OA\Get(
        summary: "List catalog elements",
        description: "Returns catalog elements with full-text search and filters by direct sections, activity, prices, price types and stock availability. Legacy sectionId is combined with sectionIds using OR. Results keep the existing sort DESC, id ASC order even when query is present.",
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated catalog elements.",
                content: new OA\JsonContent(ref: new Model(type: CatalogListResponse::class)),
            ),
            new OA\Response(response: 400, description: "Invalid query parameters."),
        ]
    )]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] CatalogListQuery $query,
        CatalogReadService $catalog,
    ): JsonResponse
    {
        return $this->json(
            $catalog->list($query->toCriteria($this->useHasNextPagePagination)),
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
                content: new OA\JsonContent(ref: new Model(type: CatalogElementResponse::class, groups: ["catalog_element:item"]))
            ),
            new OA\Response(response: 404, description: "Catalog element was not found."),
        ]
    )]
    public function item(int $id, CatalogReadService $catalog): JsonResponse
    {
        $element = $catalog->item($id);

        if ($element === null) {
            return $this->json(["message" => "Catalog element was not found."], Response::HTTP_NOT_FOUND);
        }

        return $this->json($element, context: ["groups" => ["catalog_element:item"]]);
    }

}
