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

    public function markJobProcessed(int $batchId): bool
    {
        return (bool) $this->entityManager->getConnection()->transactional(function () use ($batchId): bool {
            $this->batchRepository->incrementProcessedJobs($batchId);

            return 1 === $this->batchRepository->finishIfComplete($batchId);
        });
    }

    public function markJobFailed(int $batchId): bool
    {
        return (bool) $this->entityManager->getConnection()->transactional(function () use ($batchId): bool {
            $this->batchRepository->incrementFailedJobs($batchId);

            return 1 === $this->batchRepository->finishIfComplete($batchId);
        });
    }

    public function cancel(int $batchId): void
    {
        $this->batchRepository->cancel($batchId);
    }
}
