<?php

namespace App\Controller\Api;

use App\Entity\Stores;
use App\Repository\StoresRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/stores")]
#[OA\Tag(name: "Stores")]
class StoresController extends AbstractController
{
    #[Route("", name: "api_stores_list", methods: ["GET"])]
    #[OA\Get(
        summary: "List active stores",
        responses: [
            new OA\Response(
                response: 200,
                description: "Active stores.",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: new Model(type: Stores::class, groups: ["store:list"]))
                )
            ),
        ]
    )]
    public function list(StoresRepository $storesRepository): JsonResponse
    {
        return $this->json(
            $storesRepository->findActiveForPublicList(),
            context: ["groups" => ["store:list"]]
        );
    }

    #[Route("/{id<\d+>}", name: "api_stores_item", methods: ["GET"])]
    #[OA\Get(
        summary: "Get store",
        responses: [
            new OA\Response(
                response: 200,
                description: "Store.",
                content: new OA\JsonContent(ref: new Model(type: Stores::class, groups: ["store:item"]))
            ),
            new OA\Response(response: 404, description: "Store was not found."),
        ]
    )]
    public function item(int $id, StoresRepository $storesRepository): JsonResponse
    {
        $store = $storesRepository->findOneForPublicApi($id);

        if ($store === null) {
            return $this->json(["message" => "Store was not found."], Response::HTTP_NOT_FOUND);
        }

        return $this->json($store, context: ["groups" => ["store:item"]]);
    }
}
