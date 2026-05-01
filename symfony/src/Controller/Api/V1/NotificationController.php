<?php

namespace App\Controller\Api\V1;

use App\Dto\Listing\ListResponseDto;
use App\Dto\Sorting\ListQueryDto;
use App\Entity\Notifications;
use App\Entity\User;
use App\Repository\NotificationsRepository;
use App\Repository\Services\ListQueryNormalizer;
use App\Security\Voter\NotificationsVoter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
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

    #[Route('/notification/{id}', name: 'show', methods: ['GET'])]
    #[OA\Tag(name: 'Notifications')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Notification ID.',
        schema: new OA\Schema(type: 'integer', example: 1),
    )]
    #[OA\Response(
        response: 200,
        description: 'Notification item.',
        content: new OA\JsonContent(
            ref: new Model(type: Notifications::class, groups: ['notification:read', 'user:read'])
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Notification not found.',
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
    )]
    public function show(Notifications $notification): Response
    {
        $this->denyAccessUnlessGranted(NotificationsVoter::VIEW, $notification);

        return $this->json($notification, context: [
            'groups' => ['notification:read', 'user:read'],
        ]);
    }

    #[Route('/notification/{id}/read', name: 'mark_as_read', methods: ['PATCH'])]
    #[OA\Tag(name: 'Notifications')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Notification ID.',
        schema: new OA\Schema(type: 'integer', example: 1),
    )]
    #[OA\Response(
        response: 200,
        description: 'Notification marked as read.',
        content: new OA\JsonContent(
            ref: new Model(type: Notifications::class, groups: ['notification:read', 'user:read'])
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Notification not found.',
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
    )]
    public function markAsRead(
        Notifications $notification,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->denyAccessUnlessGranted(NotificationsVoter::MARK_AS_READ, $notification);

        $notification->markAsRead();
        $entityManager->flush();

        return $this->json($notification, context: [
            'groups' => ['notification:read', 'user:read'],
        ]);
    }

    #[Route('/notification/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Tag(name: 'Notifications')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Notification ID.',
        schema: new OA\Schema(type: 'integer', example: 1),
    )]
    #[OA\Response(
        response: 204,
        description: 'Notification deleted.',
    )]
    #[OA\Response(
        response: 404,
        description: 'Notification not found.',
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
    )]
    public function delete(
        Notifications $notification,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->denyAccessUnlessGranted(NotificationsVoter::DELETE, $notification);

        $entityManager->remove($notification);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/notification', name: 'delete_all', methods: ['DELETE'])]
    #[OA\Tag(name: 'Notifications')]
    #[OA\Response(
        response: 204,
        description: 'Current user notifications deleted.',
    )]
    public function deleteAll(NotificationsRepository $notificationsRepository): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw $this->createNotFoundException();
        }

        $notificationsRepository->deleteByRecipient($currentUser);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
