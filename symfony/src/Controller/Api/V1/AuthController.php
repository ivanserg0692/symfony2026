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
        description: 'Authenticates a user by email, password, and Cloudflare Turnstile token, returns a JWT bearer token, and sets HttpOnly access and refresh cookies for browser clients.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: AuthLoginRequestDto::class))
    )]
    #[OA\Response(
        response: 200,
        description: 'JWT token issued successfully. The response also sets HttpOnly cookies for access and refresh tokens when cookie support is enabled.',
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
        description: 'Invalid credentials or invalid Turnstile token.',
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

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    #[OA\Post(
        summary: 'Refresh JWT access token',
        description: 'Uses the refresh token from an HttpOnly cookie or request payload and issues a new access JWT. When cookie mode is enabled, the refreshed access token is also returned via Set-Cookie.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\Response(
        response: 200,
        description: 'Access token refreshed successfully.',
        content: new OA\JsonContent(
            required: ['token'],
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                new OA\Property(property: 'refresh_token', type: 'string', example: '1f0a9d1e5fd240f6a6c7d73f8f9d4b3c'),
                new OA\Property(property: 'refresh_token_expiration', type: 'integer', example: 1776297600),
            ],
            type: 'object',
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Missing, expired, or invalid refresh token.',
    )]
    public function refresh(): never
    {
        throw new \LogicException('This code should never be reached because the route is handled by the security firewall.');
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[OA\Post(
        summary: 'Logout current user',
        description: 'Logs out the current user through the security firewall, clears authentication cookies, and invalidates the refresh token when it is available to the logout request.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\Response(
        response: 204,
        description: 'Logout completed successfully.',
    )]
    public function logout(): never
    {
        throw new \LogicException('This code should never be reached because the route is handled by the security firewall.');
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get current authenticated user',
        description: 'Returns the currently authenticated user resolved from the JWT bearer token or the AUTH_TOKEN HttpOnly cookie.'
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
