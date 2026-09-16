<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\CatalogSectionsController;
use App\Entity\CatalogSections;
use App\Repository\CatalogSectionsRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CatalogSectionsControllerTest extends KernelTestCase
{
    public function testListKeepsItsSerializedResponse(): void
    {
        $section = (new CatalogSections())
            ->setId(7)
            ->setName('Section')
            ->setSlug('section')
            ->setActive(true);

        $repository = $this->createMock(CatalogSectionsRepository::class);
        $repository->expects(self::once())->method('findActiveForPublicList')->willReturn([$section]);

        $response = $this->controller()->list($repository);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([7], array_column($this->payload($response), 'id'));
        self::assertSame('Section', $this->payload($response)[0]['name']);
    }

    public function testItemKeepsItsSerializedResponse(): void
    {
        $section = (new CatalogSections())
            ->setId(7)
            ->setName('Section')
            ->setSlug('section')
            ->setActive(false);

        $repository = $this->createMock(CatalogSectionsRepository::class);
        $repository->expects(self::once())->method('findOneForPublicApi')->with(7)->willReturn($section);

        $response = $this->controller()->item(7, $repository);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(7, $this->payload($response)['id']);
        self::assertSame(false, $this->payload($response)['active']);
    }

    public function testItemStillReturnsNotFound(): void
    {
        $repository = $this->createMock(CatalogSectionsRepository::class);
        $repository->expects(self::once())->method('findOneForPublicApi')->with(404)->willReturn(null);

        $response = $this->controller()->item(404, $repository);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame(['message' => 'Catalog section was not found.'], $this->payload($response));
    }

    private function controller(): CatalogSectionsController
    {
        self::bootKernel();
        $controller = new CatalogSectionsController();
        $controller->setContainer(static::getContainer());

        return $controller;
    }

    /** @return array<mixed> */
    private function payload(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }
}
