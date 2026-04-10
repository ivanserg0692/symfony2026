<?php

namespace App\Controller\Api\V1;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/ping', name: 'api_v1_ping', methods: ['GET'])]
final class PingController
{
    #[OA\Get(
        summary: 'Ping API v1',
        description: 'Simple endpoint to verify that the v1 API is available.'
    )]
    #[OA\Tag(name: 'System')]
    #[OA\Response(
        response: 200,
        description: 'API v1 is reachable.',
        content: new OA\JsonContent(
            type: 'object',
            required: ['message', 'version'],
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'pong'),
                new OA\Property(property: 'version', type: 'string', example: 'v1'),
            ]
        )
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'pong',
            'version' => 'v1',
        ]);
    }
}
