<?php

namespace App\Controller\Api\V1;

use App\Dto\AuthLoginRequestDto;
use App\Entity\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth', name: 'api_v1_auth_')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Authenticate user and issue JWT token',
        description: 'Authenticates a user by email and password and returns a JWT bearer token.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: AuthLoginRequestDto::class))
    )]
    #[OA\Response(
        response: 200,
        description: 'JWT token issued successfully.',
        content: new OA\JsonContent(
            required: ['token'],
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
            ],
            type: 'object',
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials.'),
            ],
            type: 'object',
        )
    )]
    public function login(): never
    {
        throw new \LogicException('This code should never be reached because the route is handled by the security firewall.');
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get current authenticated user',
        description: 'Returns the currently authenticated user resolved from the JWT bearer token.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\Security(name: 'bearerAuth')]
    #[OA\Response(
        response: 200,
        description: 'Authenticated user profile.',
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:read']))
    )]
    #[OA\Response(
        response: 401,
        description: 'Missing or invalid bearer token.',
    )]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($user, context: [
            'groups' => ['user:read'],
        ]);
    }
}
