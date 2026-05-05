<?php

namespace App\News\MessageHandler;

use App\Entity\News;
use App\MessengerBatch\MessengerBatchManager;
use App\News\Message\ExportNewsMessage;
use App\News\NewsExportCsvStorage;
use App\Repository\NewsRepository;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExportNewsMessageHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly MessengerBatchManager $batchManager,
        private readonly NewsRepository $newsRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly NewsExportCsvStorage $newsExportCsvStorage,
    ) {
    }

    public function __invoke(ExportNewsMessage $message, ?Acknowledger $ack = null): mixed
    {
        if (null === $ack) {
            $this->handleSynchronously($message);

            return null;
        }

        return $this->handle($message, $ack);
    }

    private function process(array $jobs): void
    {
        $newsById = $this->findNewsByIds($this->collectNewsIds($jobs));
        $missingNewsJobs = $this->collectMissingNewsJobs($jobs, $newsById);

        try {
            $this->exportNews($this->getBatchId($jobs), $newsById);
        } catch (\Throwable $exception) {
            foreach ($jobs as [$message, $ack]) {
                \assert($message instanceof ExportNewsMessage);

                $ack->nack($exception);
            }
            return;
        }

        $this->nackMissingNewsJobs($missingNewsJobs);
        $this->ackSuccessfulJobs($jobs, $newsById);
    }

    /**
     * @return array<int, int>
     */
    private function collectNewsIds(array $jobs): array
    {
        $newsIds = [];

        foreach ($jobs as [$message]) {
            \assert($message instanceof ExportNewsMessage);

            $newsIds[$message->newsId] = $message->newsId;
        }

        return $newsIds;
    }

    /**
     * @param array<int, News> $newsById
     *
     * @return list<array{0: ExportNewsMessage, 1: Acknowledger, 2: \Throwable}>
     */
    private function collectMissingNewsJobs(array $jobs, array $newsById): array
    {
        $missingNewsJobs = [];

        foreach ($jobs as [$message, $ack]) {
            \assert($message instanceof ExportNewsMessage);

            if (isset($newsById[$message->newsId])) {
                continue;
            }

            $missingNewsJobs[] = [$message, $ack, new UnrecoverableMessageHandlingException(sprintf(
                'News "%d" was not found.',
                $message->newsId,
            ))];
        }

        return $missingNewsJobs;
    }

    /**
     * @param list<array{0: ExportNewsMessage, 1: Acknowledger, 2: \Throwable}> $missingNewsJobs
     */
    private function nackMissingNewsJobs(array $missingNewsJobs): void
    {
        foreach ($missingNewsJobs as [$message, $ack, $exception]) {
            \assert($message instanceof ExportNewsMessage);

            $ack->nack($exception);
        }
    }

    /**
     * @param array<int, News> $newsById
     */
    private function ackSuccessfulJobs(array $jobs, array $newsById): void
    {
        foreach ($jobs as [$message, $ack]) {
            \assert($message instanceof ExportNewsMessage);

            if (!isset($newsById[$message->newsId])) {
                continue;
            }

            try {
                $this->finalizeBatchIfComplete($message);
                $ack->ack();
            } catch (\Throwable $exception) {
                $ack->nack($exception);
            }
        }
    }

    private function getBatchId(array $jobs): int
    {
        foreach ($jobs as [$message]) {
            \assert($message instanceof ExportNewsMessage);

            return $message->getBatchId();
        }

        throw new \LogicException('Cannot export empty news chunk.');
    }

    /**
     * @param array<int, News> $newsById
     */
    private function exportNews(int $batchId, array $newsById): void
    {
        $exportedNews = [];
        $newsIds = array_keys($newsById);

        sort($newsIds, SORT_NUMERIC);

        foreach ($newsIds as $newsId) {
            $news = $newsById[$newsId] ?? null;

            if (null === $news) {
                continue;
            }

            $exportedNews[] = $news;
        }

        $this->newsExportCsvStorage->writeChunk($batchId, $exportedNews);
    }

    private function getBatchSize(): int
    {
        return self::BATCH_SIZE;
    }

    /**
     * @param array<int, int> $newsIds
     *
     * @return array<int, News>
     */
    private function findNewsByIds(array $newsIds): array
    {
        $newsById = [];

        foreach ($this->newsRepository->findBy(['id' => array_values($newsIds)]) as $news) {
            $newsId = $news->getId();

            if (null === $newsId) {
                continue;
            }

            $newsById[$newsId] = $news;
        }

        return $newsById;
    }

    private function handleSynchronously(ExportNewsMessage $message): void
    {
        $news = $this->newsRepository->find($message->newsId);

        if (null === $news) {
            throw new UnrecoverableMessageHandlingException(sprintf(
                'News "%d" was not found.',
                $message->newsId,
            ));
        }

        $this->exportNews($message->getBatchId(), [$message->newsId => $news]);
        $this->finalizeBatchIfComplete($message);
    }

    private function finalizeBatchIfComplete(ExportNewsMessage $message): void
    {
        if (!$this->batchManager->markJobProcessed($message->getBatchId())) {
            return;
        }

        $this->messageBus->dispatch($message->createFinalizeMessage());
    }
}
