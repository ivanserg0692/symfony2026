<?php

namespace App\Tests\Unit\Search\Product\Infrastructure;

use App\Search\Product\Infrastructure\CatalogReadFactory;
use App\Search\Product\Port\Output\CatalogReadInterface;
use PHPUnit\Framework\TestCase;

final class CatalogReadFactoryTest extends TestCase
{
    /** @dataProvider models */
    public function testOnlySelectedBackendIsConstructed(string $model): void
    {
        $reader = $this->createMock(CatalogReadInterface::class);
        $selected = static fn() => $reader;
        $unused = static function (): never {
            self::fail("The unselected backend must not be constructed.");
        };
        $factory = new CatalogReadFactory(
            $model,
            $model === "doctrine" ? $selected : $unused,
            $model === "elasticsearch" ? $selected : $unused,
        );
        self::assertSame($reader, $factory->create());
    }

    public static function models(): iterable
    {
        yield ["doctrine"];
        yield ["elasticsearch"];
    }

    public function testInvalidConfigurationIsRejected(): void
    {
        $unused = static fn() => throw new \LogicException("Must not be called.");
        $this->expectException(\InvalidArgumentException::class);
        (new CatalogReadFactory("unknown", $unused, $unused))->create();
    }

    public function testBackendFailureDoesNotFallBack(): void
    {
        $this->expectExceptionMessage("Elasticsearch unavailable");
        (new CatalogReadFactory(
            "elasticsearch",
            static fn() => throw new \LogicException("Must not fall back."),
            static fn() => throw new \RuntimeException("Elasticsearch unavailable"),
        ))->create();
    }
}
