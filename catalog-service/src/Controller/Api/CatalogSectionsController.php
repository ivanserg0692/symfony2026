<?php

namespace App\Controller\Api;

use App\Search\Product\Infrastructure\CatalogResponseFactory;
use App\Repository\CatalogSectionsRepository;
use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/catalog/sections")]
#[OA\Tag(name: "Catalog Sections")]
class CatalogSectionsController extends AbstractController
{
    public function __construct(private readonly CatalogResponseFactory $responses)
    {
    }

    #[Route("", name: "api_catalog_sections_list", methods: ["GET"])]
    #[OA\Get(
        summary: "List active catalog sections",
        description: "Returns a flat list of active catalog sections ordered by sort descending.",
        responses: [
            new OA\Response(
                response: 200,
                description: "Active catalog sections.",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: new Model(type: CatalogSectionResponse::class, groups: ["catalog_section:list"]))
                )
            ),
        ]
    )]
    public function list(CatalogSectionsRepository $sections): JsonResponse
    {
        return $this->json(
            array_map(
                fn ($section) => $this->responses->sectionFromEntity($section),
                $sections->findActiveForPublicList(),
            ),
            context: ["groups" => ["catalog_section:list"]]
        );
    }

    #[Route("/{id<\d+>}", name: "api_catalog_sections_item", methods: ["GET"])]
    #[OA\Get(
        summary: "Get catalog section",
        responses: [
            new OA\Response(
                response: 200,
                description: "Catalog section.",
                content: new OA\JsonContent(ref: new Model(type: CatalogSectionResponse::class, groups: ["catalog_section:item"]))
            ),
            new OA\Response(response: 404, description: "Catalog section was not found."),
        ]
    )]
    public function item(int $id, CatalogSectionsRepository $sections): JsonResponse
    {
        $section = $sections->findOneForPublicApi($id);

        if ($section === null) {
            return $this->json(["message" => "Catalog section was not found."], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->responses->sectionFromEntity($section),
            context: ["groups" => ["catalog_section:item"]],
        );
    }
}
