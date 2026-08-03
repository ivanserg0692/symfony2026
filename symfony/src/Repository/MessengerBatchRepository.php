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

    /**
     * Registers a new batch entity in Doctrine UnitOfWork.
     * The caller controls when the transaction is flushed.
     */
    public function save(MessengerBatch $batch): void
    {
        $this->getEntityManager()->persist($batch);
    }

    /**
     * Atomically moves a pending batch to processing state.
     * Returns the number of updated rows; 0 means the batch was already taken, cancelled, or missing.
     */
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

    /**
     * Counts one successfully handled job while the batch is still processing.
     * The conditional update keeps cancelled or already finished batches unchanged.
     */
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

    /**
     * Counts one failed job while the batch is still processing.
     * The conditional update keeps cancelled or already finished batches unchanged.
     */
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

    /**
     * Closes the batch once all jobs are counted.
     * A batch with at least one failed job becomes failed; otherwise it becomes finished.
     */
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

    /**
     * Cancels a batch that has not reached a terminal state yet.
     * Finished, failed, and already cancelled batches are left unchanged.
     */
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

    /**
     * Uses DBAL for atomic conditional updates that do not require loading the entity first.
     */
    private function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    /**
     * Reads the mapped table name so raw SQL stays aligned with Doctrine metadata.
     */
    private function getTableName(): string
    {
        return $this->getClassMetadata()->getTableName();
    }
}
