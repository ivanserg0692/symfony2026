<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\CatalogElementsController;
use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Entity\PriceType;
use App\Entity\ProductPrice;
use App\Entity\Stores;
use App\Entity\StoresElementsStocks;
use App\Search\Product\Application\CatalogReadService;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Application\Dto\Read\CatalogPage;
use App\Search\Product\Application\Dto\Read\CatalogListQuery;
use App\Search\Product\Application\ProductSearchDocumentBuilder;
use App\Search\Product\Application\Dto\Read\CatalogElementResponse;
use App\Search\Product\Port\Output\CatalogReadInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogReadContractTest extends KernelTestCase
{
    /** @dataProvider contractCases */
    public function testBothReadModelsPreserveEntityJsonContract(string $group, bool $populated): void
    {
        self::bootKernel();
        $serializer = static::getContainer()->get("serializer");
        $element = $this->element($populated);
        $expected = json_decode($serializer->serialize($element, "json", ["groups" => [$group]]), true);
        $document = (new ProductSearchDocumentBuilder())->build($element)->toArray();
        $responses = [
            CatalogElementResponse::fromEntity($element, $group === "catalog_element:item"),
            CatalogElementResponse::fromDocument($document),
        ];
        foreach ($responses as $response) {
            $actual = json_decode($serializer->serialize($response, "json", ["groups" => [$group]]), true);
            // JSON object key order is not part of the contract; array order is.
            self::assertEquals($expected, $actual);
            self::assertSame($expected["productPrices"], $actual["productPrices"]);
            self::assertArrayNotHasKey("product_id", $actual);
            self::assertArrayNotHasKey("available", $actual);
            if ($group === "catalog_element:list") {
                self::assertArrayNotHasKey("sections", $actual);
                if ($populated) {
                    self::assertArrayNotHasKey("description", $actual["productPrices"][0]["priceType"]);
                }
            } else {
                self::assertSame($expected["sections"], $actual["sections"]);
            }
        }
    }

    public static function contractCases(): iterable
    {
        foreach (["catalog_element:list", "catalog_element:item"] as $group) {
            yield [$group, false];
            yield [$group, true];
        }
    }

    /** @dataProvider queryCases */
    public function testControllerKeepsQueryNormalization(array $query, CatalogListCriteria $expected): void
    {
        self::bootKernel();
        $reader = $this->createMock(CatalogReadInterface::class);
        $reader->expects(self::once())->method("findPage")->with($expected)->willReturn(new CatalogPage([], 0, false));
        $controller = new CatalogElementsController(false);
        $controller->setContainer(static::getContainer());
        $response = $controller->list($this->query($query), new CatalogReadService($reader));
        self::assertSame(200, $response->getStatusCode());
    }

    public static function queryCases(): iterable
    {
        yield [[], new CatalogListCriteria(null, null, 1, 20, false)];
        yield [["sectionId" => 12, "active" => "false", "page" => 2, "limit" => 100], new CatalogListCriteria(12, false, 2, 100, false)];
        yield [["sectionId" => 12, "active" => "invalid", "price" => 100], new CatalogListCriteria(12, null, 1, 20, false)];
        yield [["active" => "true"], new CatalogListCriteria(null, true, 1, 20, false)];
        yield [[
            "query" => "  jacket  ",
            "sectionIds" => [5, "5", 7, ""],
            "priceFrom" => 1000,
            "priceTo" => 5000,
            "priceTypeCodes" => [" retail ", "", "retail", "promo"],
            "inStock" => "false",
        ], new CatalogListCriteria(
            sectionId: null,
            active: null,
            page: 1,
            limit: 20,
            lookAhead: false,
            query: "jacket",
            sectionIds: [5, 7],
            priceFrom: 1000,
            priceTo: 5000,
            priceTypeCodes: ["retail", "promo"],
            inStock: false,
        )];
    }

    /** @dataProvider invalidQueryCases */
    public function testRejectsInvalidListQuery(CatalogListQuery $query, string $expectedProperty): void
    {
        self::bootKernel();

        $violations = static::getContainer()->get("validator")->validate($query);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame($expectedProperty, $violations[0]->getPropertyPath());
    }

    public static function invalidQueryCases(): iterable
    {
        yield "page below one" => [new CatalogListQuery(page: 0), "page"];
        yield "limit below range" => [new CatalogListQuery(limit: 0), "limit"];
        yield "limit above range" => [new CatalogListQuery(limit: 101), "limit"];
        yield "negative minimum price" => [new CatalogListQuery(priceFrom: -1), "priceFrom"];
        yield "negative maximum price" => [new CatalogListQuery(priceTo: -1), "priceTo"];
        yield "inverted price range" => [new CatalogListQuery(priceFrom: 5001, priceTo: 5000), "priceTo"];
    }

    /** @dataProvider validBoundaryQueryCases */
    public function testAcceptsPartialPriceRangesAndEmptyArrayValues(CatalogListQuery $query): void
    {
        self::bootKernel();

        $violations = static::getContainer()->get("validator")->validate($query);

        self::assertCount(0, $violations);
    }

    public static function validBoundaryQueryCases(): iterable
    {
        yield "minimum price only" => [new CatalogListQuery(priceFrom: 1000)];
        yield "maximum price only" => [new CatalogListQuery(priceTo: 5000)];
        yield "empty array values" => [new CatalogListQuery(sectionIds: [""], priceTypeCodes: [""])];
    }

    private function query(array $query): CatalogListQuery
    {
        return new CatalogListQuery(
            sectionId: isset($query["sectionId"]) ? (int) $query["sectionId"] : null,
            active: isset($query["active"]) ? (string) $query["active"] : null,
            page: isset($query["page"]) ? (int) $query["page"] : 1,
            limit: isset($query["limit"]) ? (int) $query["limit"] : 20,
            query: isset($query["query"]) ? (string) $query["query"] : null,
            sectionIds: $query["sectionIds"] ?? [],
            priceFrom: isset($query["priceFrom"]) ? (int) $query["priceFrom"] : null,
            priceTo: isset($query["priceTo"]) ? (int) $query["priceTo"] : null,
            priceTypeCodes: $query["priceTypeCodes"] ?? [],
            inStock: isset($query["inStock"]) ? (string) $query["inStock"] : null,
        );
    }

    public function testItemUsesResponseDtoAndKeepsNotFoundResponse(): void
    {
        self::bootKernel();
        $element = $this->element(true);
        $dto = CatalogElementResponse::fromDocument((new ProductSearchDocumentBuilder())->build($element)->toArray());
        $reader = $this->createMock(CatalogReadInterface::class);
        $reader->expects(self::exactly(2))->method("findById")->withConsecutive([42], [99])->willReturnOnConsecutiveCalls($dto, null);
        $controller = new CatalogElementsController(false);
        $controller->setContainer(static::getContainer());
        $service = new CatalogReadService($reader);
        $response = $controller->item(42, $service);
        $expected = static::getContainer()->get("serializer")->serialize($element, "json", ["groups" => ["catalog_element:item"]]);
        self::assertJsonStringEqualsJsonString($expected, $response->getContent());
        $response = $controller->item(99, $service);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"message":"Catalog element was not found."}', $response->getContent());
    }

    public function testListSerializesOnlyListFieldsInBothPaginationModes(): void
    {
        self::bootKernel();
        $element = $this->element(true);
        $dto = CatalogElementResponse::fromDocument((new ProductSearchDocumentBuilder())->build($element)->toArray());
        $serializer = static::getContainer()->get("serializer");
        foreach ([false, true] as $lookAhead) {
            $reader = $this->createMock(CatalogReadInterface::class);
            $reader->expects(self::once())->method("findPage")
                ->willReturn(new CatalogPage([$dto], $lookAhead ? null : 5, $lookAhead));
            $controller = new CatalogElementsController($lookAhead);
            $controller->setContainer(static::getContainer());
            $response = $controller->list(new CatalogListQuery(), new CatalogReadService($reader));
            $pagination = ["page" => 1, "limit" => 20] + ($lookAhead ? ["hasNextPage" => true] : ["total" => 5]);
            $expected = $serializer->serialize(["items" => [$element], "pagination" => $pagination],
                "json", ["groups" => ["catalog_element:list"]]);
            self::assertJsonStringEqualsJsonString($expected, $response->getContent());
        }
    }

    private function element(bool $populated): CatalogElements
    {
        $element = (new CatalogElements())->setName("Товар")->setSlug("product")->setActive(false)->setSort(0);
        (new \ReflectionProperty($element, "id"))->setValue($element, 42);
        $element->getProduct()->setId(142);
        if (!$populated) {
            return $element;
        }
        $element->setDescription("Описание")->setPictureId("picture");
        $parent = (new CatalogSections())->setId(1);
        $element->addSection((new CatalogSections())->setId(2)->setName("Раздел")->setSlug("section")
            ->setActive(false)->setDescription("Описание раздела")->setPictureId("section-picture")->setParent($parent)->setSort(0));
        $element->addSection((new CatalogSections())->setId(3)->setName("Другой")->setSlug("other")->setActive(true));
        foreach ([5, 6] as $id) {
            $type = (new PriceType())->setCode("type-".$id)->setName("Цена")->setActive(false)->setSort(0)
                ->setDescription($id === 5 ? "Описание цены" : null);
            (new \ReflectionProperty($type, "id"))->setValue($type, $id);
            $price = (new ProductPrice())->setPriceType($type)->setPrice(0)->setCurrency("RUB")->setActive(false)
                ->setValidFrom(new \DateTimeImmutable("2026-09-14T10:20:30+08:00"))
                ->setValidTo($id === 5 ? new \DateTimeImmutable("2026-10-14T10:20:30+08:00") : null);
            (new \ReflectionProperty($price, "id"))->setValue($price, $id + 10);
            $element->addProductPrice($price);
        }
        foreach ([1 => 5, 2 => -2] as $id => $stock) {
            $element->addStoreStock((new StoresElementsStocks())
                ->setStore((new Stores())->setId($id)->setActive(false))->setStock($stock));
        }

        return $element;
    }
}
