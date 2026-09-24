<?php

namespace App\Tests\Integration\Search\Product\Infrastructure;

use App\Search\Product\Infrastructure\Doctrine\PostgreSqlProductSearchReindexLock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PostgreSqlProductSearchReindexLockTest extends KernelTestCase
{
    public function testSharedIncrementalLocksAreConcurrentAndConflictWithExclusiveReindexLock(): void
    {
        self::bootKernel();

        $primaryConnection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $secondaryConnection = DriverManager::getConnection($primaryConnection->getParams());
        $lockId = random_int(1_000_000_000, 2_000_000_000);
        $primaryLock = new PostgreSqlProductSearchReindexLock($primaryConnection, $lockId);
        $secondaryLock = new PostgreSqlProductSearchReindexLock($secondaryConnection, $lockId);

        try {
            self::assertTrue($primaryLock->acquireShared());
            self::assertTrue($secondaryLock->acquireShared());

            $secondaryLock->releaseShared();
            self::assertFalse($secondaryLock->acquire());

            $primaryLock->releaseShared();
            self::assertTrue($secondaryLock->acquire());
            self::assertFalse($primaryLock->acquireShared());
        } finally {
            $primaryLock->releaseShared();
            $primaryLock->release();
            $secondaryLock->releaseShared();
            $secondaryLock->release();
            $secondaryConnection->close();
        }
    }
}
