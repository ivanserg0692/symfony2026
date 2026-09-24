<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Doctrine\IncrementalIndexing;

use App\Search\Product\Infrastructure\Doctrine\IncrementalIndexing\ProductSearchOutboxEntityManagerDecorator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProductSearchOutboxEntityManagerDecoratorTest extends TestCase
{
    public function testItWrapsOneFlushInAnOuterTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method("isTransactionActive")->willReturn(false);
        $connection->expects(self::once())->method("beginTransaction");
        $connection->expects(self::once())->method("commit");
        $connection->expects(self::never())->method("rollBack");

        $wrapped = $this->createMock(EntityManagerInterface::class);
        $wrapped->method("getConnection")->willReturn($connection);
        $wrapped->expects(self::once())->method("flush");

        (new ProductSearchOutboxEntityManagerDecorator($wrapped))->flush();
    }

    public function testItDoesNotOwnAnExistingTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method("isTransactionActive")->willReturn(true);
        $connection->expects(self::never())->method("beginTransaction");
        $connection->expects(self::never())->method("commit");
        $connection->expects(self::never())->method("rollBack");

        $wrapped = $this->createMock(EntityManagerInterface::class);
        $wrapped->method("getConnection")->willReturn($connection);
        $wrapped->expects(self::once())->method("flush");

        (new ProductSearchOutboxEntityManagerDecorator($wrapped))->flush();
    }

    public function testItRollsBackItsTransactionAndPropagatesFlushFailure(): void
    {
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
        $wrapped->expects(self::once())->method("flush")->willThrowException($failure);

        $this->expectExceptionObject($failure);

        (new ProductSearchOutboxEntityManagerDecorator($wrapped))->flush();
    }
}
