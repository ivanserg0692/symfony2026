<?php

namespace App\MessengerBatch;

use App\Entity\MessengerBatch;
use App\Repository\MessengerBatchRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MessengerBatchManager
{
    public function __construct(
        private MessengerBatchRepository $batchRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(int $totalJobs): MessengerBatch
    {
        if ($totalJobs < 0) {
            throw new \InvalidArgumentException('Batch total jobs cannot be negative.');
        }

        $batch = new MessengerBatch()
            ->setTotalJobs($totalJobs);

        $this->batchRepository->save($batch);
        $this->entityManager->flush();

        return $batch;
    }

    public function start(int $batchId): void
    {
        $this->batchRepository->start($batchId);
    }

    public function markJobProcessed(int $batchId): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($batchId): void {
            $this->batchRepository->incrementProcessedJobs($batchId);
            $this->batchRepository->finishIfComplete($batchId);
        });
    }

    public function markJobFailed(int $batchId): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($batchId): void {
            $this->batchRepository->incrementFailedJobs($batchId);
            $this->batchRepository->finishIfComplete($batchId);
        });
    }

    public function cancel(int $batchId): void
    {
        $this->batchRepository->cancel($batchId);
    }
}
