<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\CatalogElementsController;
use App\Repository\CatalogElementsRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class CatalogElementsControllerTest extends KernelTestCase
{
    public function testListReturnsTotalInDefaultPaginationMode(): void
    {
        $repository = $this->createMock(CatalogElementsRepository::class);
        $repository
            ->expects(self::once())
            ->method("findPageIds")
            ->with(null, null, 2, 2, false)
            ->willReturn([3, 4]);
        $repository
            ->expects(self::once())
            ->method("findListByIds")
            ->with([3, 4])
            ->willReturn([]);
        $repository
            ->expects(self::once())
            ->method("countMatchingListFilters")
            ->with(null, null)
            ->willReturn(5);

        $payload = $this->requestList(new CatalogElementsController(false), $repository);

        self::assertSame(
            ["page" => 2, "limit" => 2, "total" => 5],
            $payload["pagination"],
        );
    }

    public function testListReturnsHasNextPageWithoutCountingInLookAheadMode(): void
    {
        $repository = $this->createMock(CatalogElementsRepository::class);
        $repository
            ->expects(self::once())
            ->method("findPageIds")
            ->with(null, null, 2, 2, true)
            ->willReturn([3, 4, 5]);
        $repository
            ->expects(self::once())
            ->method("findListByIds")
            ->with([3, 4])
            ->willReturn([]);
        $repository
            ->expects(self::never())
            ->method("countMatchingListFilters");

        $payload = $this->requestList(new CatalogElementsController(true), $repository);

        self::assertSame(
            ["page" => 2, "limit" => 2, "hasNextPage" => true],
            $payload["pagination"],
        );
    }

    public function testListReturnsFalseWhenLookAheadModeFindsNoExtraItem(): void
    {
        $repository = $this->createMock(CatalogElementsRepository::class);
        $repository
            ->expects(self::once())
            ->method("findPageIds")
            ->with(null, null, 2, 2, true)
            ->willReturn([3, 4]);
        $repository
            ->expects(self::once())
            ->method("findListByIds")
            ->with([3, 4])
            ->willReturn([]);
        $repository
            ->expects(self::never())
            ->method("countMatchingListFilters");

        $payload = $this->requestList(new CatalogElementsController(true), $repository);

        self::assertSame(
            ["page" => 2, "limit" => 2, "hasNextPage" => false],
            $payload["pagination"],
        );
    }

    /**
     * @return array{items: array<mixed>, pagination: array<string, bool|int>}
     */
    private function requestList(
        CatalogElementsController $controller,
        CatalogElementsRepository $repository,
    ): array {
        self::bootKernel();
        $controller->setContainer(static::getContainer());

        $request = Request::create("/api/catalog/elements", "GET", ["page" => 2, "limit" => 2]);
        $response = $controller->list($request, $repository);
        $content = $response->getContent();

        self::assertIsString($content);

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }
}
