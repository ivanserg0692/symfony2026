<?php

namespace App\Controller\Api\V1;

use App\Dto\AuthLoginRequestDto;
use App\Entity\User;
use App\Security\Csrf\EnvAwareStatelessCsrfTokenManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth', name: 'api_v1_auth_')]
final class AuthController extends AbstractController
{
    #[Route('/csrf', name: 'csrf', methods: ['GET'])]
    #[OA\Get(
        summary: 'Issue CSRF token for auth endpoints',
        description: 'Generates a stateless CSRF token, stores its companion cookie on the API domain, and returns the token that must be sent in the configured CSRF header. Use id=api_mutation for unsafe API methods such as DELETE.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\Parameter(
        name: 'id',
        in: 'query',
        required: false,
        description: 'CSRF token id. Defaults to authenticate.',
        schema: new OA\Schema(type: 'string', enum: ['authenticate', 'api_mutation']),
        example: 'api_mutation',
    )]
    #[OA\Response(
        response: 200,
        description: 'CSRF token issued successfully.',
        content: new OA\JsonContent(
            required: ['token', 'token_id', 'header_name', 'cookie_name'],
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'ea9f28f0d5e34ce3b0900fca1e5b7d8ea4f35f2c4e5d7f8a3c2b1d0e9f7a6b5c'),
                new OA\Property(property: 'token_id', type: 'string', example: 'api_mutation'),
                new OA\Property(property: 'header_name', type: 'string', example: 'X-CSRF-Token'),
                new OA\Property(property: 'cookie_name', type: 'string', example: 'csrf-token'),
            ],
            type: 'object',
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Unsupported CSRF token id.',
    )]
    public function csrf(Request $request, EnvAwareStatelessCsrfTokenManager $csrfTokenManager): JsonResponse
    {
        $tokenId = $request->query->getString('id', 'authenticate');

        if (!\in_array($tokenId, ['authenticate', 'api_mutation'], true)) {
            throw new BadRequestHttpException('Unsupported CSRF token id.');
        }

        $token = $csrfTokenManager->refreshToken($tokenId)->getValue();
        $response = $this->json([
            'token' => $token,
            'token_id' => $tokenId,
            'header_name' => $csrfTokenManager->getHeaderName(),
            'cookie_name' => $csrfTokenManager->getCookieName(),
        ]);

        $csrfTokenManager->clearTokenCookies($request, $response);
        $response->headers->setCookie($csrfTokenManager->createTokenCookie($request, $token));

        return $response;
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Authenticate user and issue JWT token',
        description: 'Authenticates a user by email, password, and Cloudflare Turnstile token, returns a JWT bearer token, and sets HttpOnly access and refresh cookies for browser clients. Call GET /api/v1/auth/csrf first and send the returned token in the CSRF header.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\Parameter(
        name: 'X-CSRF-Token',
        in: 'header',
        required: true,
        description: 'CSRF token returned by GET /api/v1/auth/csrf.',
        schema: new OA\Schema(type: 'string'),
        example: 'ea9f28f0d5e34ce3b0900fca1e5b7d8ea4f35f2c4e5d7f8a3c2b1d0e9f7a6b5c'
    )]
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
        description: 'Uses the refresh token from an HttpOnly cookie or request payload and issues a new access JWT. Call GET /api/v1/auth/csrf first and send the returned token in the CSRF header.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\Parameter(
        name: 'X-CSRF-Token',
        in: 'header',
        required: true,
        description: 'CSRF token returned by GET /api/v1/auth/csrf.',
        schema: new OA\Schema(type: 'string'),
        example: 'ea9f28f0d5e34ce3b0900fca1e5b7d8ea4f35f2c4e5d7f8a3c2b1d0e9f7a6b5c'
    )]
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
        description: 'Logs out the current user through the security firewall, clears authentication cookies, and invalidates the refresh token when it is available to the logout request. Call GET /api/v1/auth/csrf first and send the returned token in the CSRF header.'
    )]
    #[OA\Tag(name: 'Auth')]
    #[OA\Parameter(
        name: 'X-CSRF-Token',
        in: 'header',
        required: true,
        description: 'CSRF token returned by GET /api/v1/auth/csrf.',
        schema: new OA\Schema(type: 'string'),
        example: 'ea9f28f0d5e34ce3b0900fca1e5b7d8ea4f35f2c4e5d7f8a3c2b1d0e9f7a6b5c'
    )]
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
