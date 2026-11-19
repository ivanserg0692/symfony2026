<?php

namespace App\Controller\Api;

use App\Media\Infrastructure\CatalogMediaStorage;
use App\Repository\CatalogMediaRepository;
use Aws\S3\Exception\S3Exception;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogMediaController extends AbstractController
{
    #[Route('/api/catalog/media/{key}', name: 'api_catalog_media', methods: ['GET', 'HEAD'], requirements: ['key' => '.+'])]
    #[OA\Get(summary: 'Get a catalog product photograph', tags: ['Catalog Media'], responses: [new OA\Response(response: 200, description: 'Registered WebP catalog photograph.', content: new OA\MediaType(mediaType: 'image/webp')), new OA\Response(response: 304, description: 'Photograph has not changed.'), new OA\Response(response: 404, description: 'Photograph not found.')])]
    #[OA\Head(summary: 'Get catalog photograph headers', tags: ['Catalog Media'], responses: [new OA\Response(response: 200, description: 'WebP photograph headers without a response body.'), new OA\Response(response: 304, description: 'Photograph has not changed.'), new OA\Response(response: 404, description: 'Photograph not found.')])]
    public function __invoke(string $key, Request $request, CatalogMediaRepository $media, CatalogMediaStorage $storage): Response
    {
        // Only registered derivatives are reachable. Sources stay private.
        $file = $media->findOneByVariantKey($key)?->getVariantByKey($key);
        if ($file === null) {
            throw new NotFoundHttpException('Catalog photograph not found.');
        }
        $response = new StreamedResponse();
        $response->headers->set('Content-Type', 'image/webp');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic()->setMaxAge(31536000)->setImmutable()->setEtag($file->sha256);
        if ($response->isNotModified($request)) {
            return $response;
        }
        try {
            if ($request->isMethod('HEAD')) {
                $storage->checkExists($file->key);
                return $response;
            }
            $stream = $storage->read($file->key);
        } catch (S3Exception $exception) {
            if ($exception->getStatusCode() === 404) {
                throw new NotFoundHttpException('Catalog photograph not uploaded.', $exception);
            }
            throw $exception;
        }
        $response->setCallback(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        });
        return $response;
    }
}
