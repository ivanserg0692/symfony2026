<?php

namespace App\Controller;

use App\Dto\ListQueryDto;
use App\Entity\News;
use App\Repository\NewsRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    #[Route('/api/v1/news', name: 'app_news', methods: ['GET'])]
    #[OA\Tag(name: 'News')]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of news.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: News::class, groups: ['news:read', 'user:read'])),
                ),
                new OA\Property(
                    property: 'pagination',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 42),
                        new OA\Property(property: 'pages', type: 'integer', example: 5),
                    ],
                ),
                new OA\Property(
                    property: 'sorting',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'sort', type: 'string', example: 'createdAt'),
                        new OA\Property(property: 'direction', type: 'string', example: 'DESC'),
                    ],
                ),
            ],
        ),
    )]
    public function index(
        #[MapQueryString] ListQueryDto $query,
        NewsRepository $repository,
    ): JsonResponse
    {
        $pager = new Pagerfanta(new QueryAdapter($repository->createListQueryBuilder($query)));
        $pager->setMaxPerPage($repository->normalizeLimit($query->limit));
        $pager->setCurrentPage($repository->normalizePage($query->page));

        return $this->json([
            'items' => iterator_to_array($pager->getCurrentPageResults()),
            'pagination' => [
                'page' => $pager->getCurrentPage(),
                'limit' => $pager->getMaxPerPage(),
                'total' => $pager->getNbResults(),
                'pages' => $pager->getNbPages(),
            ],
            'sorting' => [
                'sort' => $repository->normalizeSort($query->sort),
                'direction' => $repository->normalizeDirection($query->direction),
            ],
        ], context: [
            'groups' => ['news:read', 'user:read'],
        ]);
    }
}
