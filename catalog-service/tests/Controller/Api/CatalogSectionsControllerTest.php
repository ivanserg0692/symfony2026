<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\CatalogSectionsController;
use App\Entity\CatalogSections;
use App\Search\Product\Application\Dto\Read\CatalogSectionResponse;
use App\Search\Product\Port\Input\CatalogSectionReadInputInterface;
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

        $sections = $this->createMock(CatalogSectionReadInputInterface::class);
        $sections->expects(self::once())->method('list')->willReturn([CatalogSectionResponse::fromEntity($section)]);

        $response = $this->controller()->list($sections);

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

        $sections = $this->createMock(CatalogSectionReadInputInterface::class);
        $sections->expects(self::once())->method('item')->with(7)->willReturn(CatalogSectionResponse::fromEntity($section));

        $response = $this->controller()->item(7, $sections);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(7, $this->payload($response)['id']);
        self::assertSame(false, $this->payload($response)['active']);
    }

    public function testItemStillReturnsNotFound(): void
    {
        $sections = $this->createMock(CatalogSectionReadInputInterface::class);
        $sections->expects(self::once())->method('item')->with(404)->willReturn(null);

        $response = $this->controller()->item(404, $sections);

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
