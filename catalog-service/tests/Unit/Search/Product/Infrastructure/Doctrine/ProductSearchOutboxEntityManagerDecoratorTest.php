<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Doctrine;

use App\Entity\ProductSearchOutboxEvent;
use App\Search\Product\Infrastructure\Doctrine\CatalogElementOutboxCollector;
use App\Search\Product\Infrastructure\Doctrine\ProductSearchOutboxEntityManagerDecorator;
use App\Search\Product\Infrastructure\Doctrine\ProductSearchOutboxWriter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProductSearchOutboxEntityManagerDecoratorTest extends TestCase
{
    public function testCollectorRejectsIdentityGeneratedOutsideCoordinatedFlush(): void
    {
        $collector = new CatalogElementOutboxCollector();

        $this->expectException(\LogicException::class);
        $collector->collect(42);
    }

    public function testItPerformsSecondFlushForCollectedCatalogElementIds(): void
    {
        $collector = new CatalogElementOutboxCollector();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method("isTransactionActive")->willReturn(false);
        $connection->expects(self::once())->method("beginTransaction");
        $connection->expects(self::once())->method("commit");
        $connection->expects(self::never())->method("rollBack");

        $flushNumber = 0;
        $persistedEvent = null;
        $wrapped = $this->createMock(EntityManagerInterface::class);
        $wrapped->method("getConnection")->willReturn($connection);
        $wrapped->expects(self::exactly(2))
            ->method("flush")
            ->willReturnCallback(function () use (&$flushNumber, $collector): void {
                ++$flushNumber;
                if ($flushNumber === 1) {
                    $collector->collect(42);
                }
            });
        $wrapped->expects(self::once())
            ->method("persist")
            ->willReturnCallback(function (object $entity) use (&$persistedEvent): void {
                $persistedEvent = $entity;
            });

        $this->decorator($wrapped, $collector)->flush();

        self::assertInstanceOf(ProductSearchOutboxEvent::class, $persistedEvent);
        self::assertSame(42, $persistedEvent->getCatalogElementId());
    }

    public function testItDoesNotPerformSecondFlushWhenNothingWasCollected(): void
    {
        $collector = new CatalogElementOutboxCollector();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method("isTransactionActive")->willReturn(false);
        $connection->expects(self::once())->method("beginTransaction");
        $connection->expects(self::once())->method("commit");

        $wrapped = $this->createMock(EntityManagerInterface::class);
        $wrapped->method("getConnection")->willReturn($connection);
        $wrapped->expects(self::once())->method("flush");
        $wrapped->expects(self::never())->method("persist");

        $this->decorator($wrapped, $collector)->flush();
    }

    public function testItKeepsBothFlushesInsideAnExistingTransaction(): void
    {
        $collector = new CatalogElementOutboxCollector();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method("isTransactionActive")->willReturn(true);
        $connection->expects(self::never())->method("beginTransaction");
        $connection->expects(self::never())->method("commit");
        $connection->expects(self::never())->method("rollBack");

        $flushNumber = 0;
        $wrapped = $this->createMock(EntityManagerInterface::class);
        $wrapped->method("getConnection")->willReturn($connection);
        $wrapped->expects(self::exactly(2))
            ->method("flush")
            ->willReturnCallback(function () use (&$flushNumber, $collector): void {
                ++$flushNumber;
                if ($flushNumber === 1) {
                    $collector->collect(84);
                }
            });
        $wrapped->expects(self::once())->method("persist");

        $this->decorator($wrapped, $collector)->flush();
    }

    public function testItRollsBackOwnedTransactionAndDiscardsIdsAfterFailedFlush(): void
    {
        $collector = new CatalogElementOutboxCollector();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))
            ->method("isTransactionActive")
            ->willReturnOnConsecutiveCalls(false, true);
        $connection->expects(self::once())->method("beginTransaction");
        $connection->expects(self::never())->method("commit");
        $connection->expects(self::once())->method("rollBack");

        $failure = new \RuntimeException("Flush failed.");
        $wrapped = $this->createMock(EntityManagerInterface::class);
        $wrapped->method("getConnection")->willReturn($connection);
        $wrapped->expects(self::once())
            ->method("flush")
            ->willReturnCallback(function () use ($collector, $failure): void {
                $collector->collect(126);

                throw $failure;
            });
        $wrapped->expects(self::never())->method("persist");

        try {
            $this->decorator($wrapped, $collector)->flush();
            self::fail("The wrapped flush exception must be propagated.");
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        $collector->begin();
        self::assertSame([], $collector->release());
    }

    private function decorator(
        EntityManagerInterface $wrapped,
        CatalogElementOutboxCollector $collector,
    ): ProductSearchOutboxEntityManagerDecorator {
        return new ProductSearchOutboxEntityManagerDecorator(
            $wrapped,
            $collector,
            new ProductSearchOutboxWriter(),
        );
    }
}
