<?php

namespace App\Controller\Api\V1;

use App\Dto\Listing\ListResponseDto;
use App\Dto\Sorting\ListQueryDto;
use App\Entity\News;
use App\Entity\User;
use App\Repository\NewsRepository;
use App\Repository\Services\ListQueryNormalizer;
use App\Security\Voter\NewsVoter;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    #[Route('/api/v1/news', name: 'app_news', methods: ['GET'])]
    #[OA\Tag(name: 'News')]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of news.',
        content: new OA\JsonContent(
            allOf: [
                new OA\Schema(ref: new Model(type: ListResponseDto::class)),
                new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: News::class, groups: ['news:read', 'user:read', 'status:read'])),
                        ),
                    ],
                ),
            ],
        ),
    )]
    public function index(
        #[MapQueryString] ListQueryDto $query,
        NewsRepository $repository,
        ListQueryNormalizer $listQueryNormalizer,
    ): JsonResponse
    {
        $currentUser = $this->getUser();
        $pager = new Pagerfanta(new QueryAdapter($repository->createListQueryBuilder(
            $query,
            $currentUser instanceof User ? $currentUser : null,
        )));
        $pager->setMaxPerPage($listQueryNormalizer->normalizeLimit($query->limit));
        $pager->setCurrentPage($listQueryNormalizer->normalizePage($query->page));

        return $this->json(ListResponseDto::fromPager(
            $pager,
            $listQueryNormalizer->normalizeSort(
                $query->sort,
                NewsRepository::ALLOWED_SORTS,
                NewsRepository::DEFAULT_SORT,
            ),
            $listQueryNormalizer->normalizeDirection($query->direction),
        ), context: [
            'groups' => ['news:read', 'user:read', 'status:read'],
        ]);
    }

    #[Route('/api/v1/news/{slug}', name: 'app_news_show', methods: ['GET'])]
    #[OA\Tag(name: 'News')]
    #[OA\Parameter(
        name: 'slug',
        in: 'path',
        required: true,
        description: 'News slug.',
        schema: new OA\Schema(type: 'string', example: 'news-title'),
    )]
    #[OA\Response(
        response: 200,
        description: 'News item.',
        content: new OA\JsonContent(
            ref: new Model(type: News::class, groups: ['news:read', 'user:read', 'status:read'])
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'News not found.',
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
    )]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] News $news,
    ): JsonResponse
    {
        if (!$this->isGranted(NewsVoter::VIEW, $news)) {
            throw $this->createNotFoundException();
        }

        return $this->json($news, context: [
            'groups' => ['news:read', 'user:read', 'status:read'],
        ]);
    }
}
