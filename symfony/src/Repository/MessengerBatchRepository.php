<?php

namespace App\Repository;

use App\Entity\MessengerBatch;
use App\MessengerBatch\MessengerBatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessengerBatch>
 */
class MessengerBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessengerBatch::class);
    }

    public function save(MessengerBatch $batch): void
    {
        $this->getEntityManager()->persist($batch);
    }

    public function start(int $batchId): int
    {
        return $this->getConnection()->executeStatement(sprintf(
            'UPDATE %s SET status = :processingStatus, started_at = COALESCE(started_at, :startedAt) WHERE id = :id AND status = :pendingStatus',
            $this->getTableName(),
        ), [
            'id' => $batchId,
            'processingStatus' => MessengerBatchStatus::PROCESSING->value,
            'pendingStatus' => MessengerBatchStatus::PENDING->value,
            'startedAt' => new \DateTimeImmutable(),
        ], [
            'startedAt' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function incrementProcessedJobs(int $batchId): int
    {
        return $this->getConnection()->executeStatement(sprintf(
            'UPDATE %s SET processed_jobs = processed_jobs + 1 WHERE id = :id AND status = :processingStatus',
            $this->getTableName(),
        ), [
            'id' => $batchId,
            'processingStatus' => MessengerBatchStatus::PROCESSING->value,
        ]);
    }

    public function incrementFailedJobs(int $batchId): int
    {
        return $this->getConnection()->executeStatement(sprintf(
            'UPDATE %s SET failed_jobs = failed_jobs + 1 WHERE id = :id AND status = :processingStatus',
            $this->getTableName(),
        ), [
            'id' => $batchId,
            'processingStatus' => MessengerBatchStatus::PROCESSING->value,
        ]);
    }

    public function finishIfComplete(int $batchId): int
    {
        return $this->getConnection()->executeStatement(sprintf(
            'UPDATE %s SET status = CASE WHEN failed_jobs > 0 THEN :failedStatus ELSE :finishedStatus END, finished_at = COALESCE(finished_at, :finishedAt) WHERE id = :id AND status = :processingStatus AND processed_jobs + failed_jobs >= total_jobs',
            $this->getTableName(),
        ), [
            'id' => $batchId,
            'processingStatus' => MessengerBatchStatus::PROCESSING->value,
            'failedStatus' => MessengerBatchStatus::FAILED->value,
            'finishedStatus' => MessengerBatchStatus::FINISHED->value,
            'finishedAt' => new \DateTimeImmutable(),
        ], [
            'finishedAt' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function cancel(int $batchId): int
    {
        return $this->getConnection()->executeStatement(sprintf(
            'UPDATE %s SET status = :cancelledStatus, cancelled_at = COALESCE(cancelled_at, :cancelledAt) WHERE id = :id AND status IN (:pendingStatus, :processingStatus)',
            $this->getTableName(),
        ), [
            'id' => $batchId,
            'cancelledStatus' => MessengerBatchStatus::CANCELLED->value,
            'pendingStatus' => MessengerBatchStatus::PENDING->value,
            'processingStatus' => MessengerBatchStatus::PROCESSING->value,
            'cancelledAt' => new \DateTimeImmutable(),
        ], [
            'cancelledAt' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    private function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    private function getTableName(): string
    {
        return $this->getClassMetadata()->getTableName();
    }
}
