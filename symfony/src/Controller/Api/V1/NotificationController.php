<?php

namespace App\Controller\Api\V1;

use App\Dto\Listing\ListResponseDto;
use App\Dto\Sorting\ListQueryDto;
use App\Entity\Notifications;
use App\Repository\NotificationsRepository;
use App\Repository\Services\ListQueryNormalizer;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_v1_notification_')]
final class NotificationController extends AbstractController
{
    #[Route('/notification', name: 'notification', methods: ['GET'])]
    #[OA\Tag(name: 'Notifications')]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of current user notifications.',
        content: new OA\JsonContent(
            allOf: [
                new OA\Schema(ref: new Model(type: ListResponseDto::class)),
                new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: Notifications::class, groups: ['notification:read', 'user:read'])),
                        ),
                    ],
                ),
            ],
        ),
    )]
    public function index(
        #[MapQueryString] ListQueryDto $query,
        NotificationsRepository        $notificationsRepository,
        ListQueryNormalizer            $listQueryNormalizer,
    ): Response
    {
        $currentUser = $this->getUser();
        $pager = new Pagerfanta(new QueryAdapter($notificationsRepository->createListQueryBuilder($query, $currentUser)));
        $pager->setMaxPerPage($listQueryNormalizer->normalizeLimit($query->limit));
        $pager->setCurrentPage($listQueryNormalizer->normalizePage($query->page));
        return $this->json(
            ListResponseDto::fromPager($pager, $listQueryNormalizer->normalizeSort(
                $query->sort,
                NotificationsRepository::ALLOWED_SORTS,
                NotificationsRepository::DEFAULT_SORT,
            ),
                $listQueryNormalizer->normalizeDirection($query->direction)),
            context: [
                'groups' => ['notification:read', 'user:read'],
            ]
        );
    }
}
