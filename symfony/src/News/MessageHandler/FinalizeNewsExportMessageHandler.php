<?php

namespace App\News\MessageHandler;

use App\MessengerBatch\MessengerBatchStatus;
use App\News\Message\FinalizeNewsExportMessage;
use App\News\NewsExportCsvStorage;
use App\Repository\NewsExportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class FinalizeNewsExportMessageHandler
{
    public function __construct(
        private NewsExportRepository $newsExportRepository,
        private NewsExportCsvStorage $newsExportCsvStorage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(FinalizeNewsExportMessage $message): void
    {
        $newsExport = $this->newsExportRepository->find($message->batchId);

        if (null === $newsExport) {
            throw new UnrecoverableMessageHandlingException(sprintf(
                'News export "%d" was not found.',
                $message->batchId,
            ));
        }

        if (MessengerBatchStatus::FAILED->value === $newsExport->getMessengerBatch()->getStatus()) {
            $this->newsExportCsvStorage->deleteChunks($message->batchId);

            return;
        }

        if (MessengerBatchStatus::FINISHED->value !== $newsExport->getMessengerBatch()->getStatus()) {
            return;
        }

        $newsExport->setFilePath($this->newsExportCsvStorage->finalize($message->batchId));
        $this->entityManager->flush();
    }
}
