<?php

namespace App\Controller\Api\V1;

use App\Dto\Listing\ListResponseDto;
use App\Dto\Sorting\SearchListQueryDto;
use App\Entity\User;
use App\Repository\Services\ListQueryNormalizer;
use App\Repository\UserRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class UsersController extends AbstractController
{
    #[Route('/api/v1/users', name: 'api_v1_users_index', methods: ['GET'])]
    #[OA\Get(
        summary: 'List users',
        description: 'Returns user identifiers for local service fixtures and integration checks.'
    )]
    #[OA\Tag(name: 'Users')]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of users.',
        content: new OA\JsonContent(
            allOf: [
                new OA\Schema(ref: new Model(type: ListResponseDto::class)),
                new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: User::class, groups: ['User:exportList'])),
                        ),
                    ],
                ),
            ],
        ),
    )]
    public function index(
        #[MapQueryString] SearchListQueryDto $query,
        UserRepository $repository,
        ListQueryNormalizer $listQueryNormalizer,
    ): JsonResponse
    {
        $pager = new Pagerfanta(new QueryAdapter($repository->createListQueryBuilder($query)));
        $pager->setMaxPerPage($listQueryNormalizer->normalizeLimit($query->limit));
        $pager->setCurrentPage($listQueryNormalizer->normalizePage($query->page));

        return $this->json(ListResponseDto::fromPager(
            $pager,
            $listQueryNormalizer->normalizeSort(
                $query->sort,
                UserRepository::ALLOWED_SORTS,
                UserRepository::DEFAULT_SORT,
            ),
            $listQueryNormalizer->normalizeDirection($query->direction),
        ), context: [
            'groups' => ['User:exportList'],
        ]);
    }
}
