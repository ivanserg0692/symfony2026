<?php

namespace App\News\MessageHandler;

use App\Entity\News;
use App\MessengerBatch\MessengerBatchManager;
use App\News\Message\ExportNewsMessage;
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
        $newsIds = [];

        foreach ($jobs as [$message]) {
            \assert($message instanceof ExportNewsMessage);

            $newsIds[$message->newsId] = $message->newsId;
        }

        $newsById = $this->findNewsByIds($newsIds);

        foreach ($jobs as [$message, $ack]) {
            \assert($message instanceof ExportNewsMessage);

            $news = $newsById[$message->newsId] ?? null;

            if (null === $news) {
                $ack->nack(new UnrecoverableMessageHandlingException(sprintf(
                    'News "%d" was not found.',
                    $message->newsId,
                )));

                continue;
            }

            try {
                $this->exportNews($news);
                $this->finalizeBatchIfComplete($message);
                $ack->ack();
            } catch (\Throwable $exception) {
                $ack->nack($exception);
            }
        }
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

        $this->exportNews($news);
        $this->finalizeBatchIfComplete($message);
    }

    private function exportNews(News $news): void
    {
        throw new \LogicException('News export implementation is not configured.');
    }

    private function finalizeBatchIfComplete(ExportNewsMessage $message): void
    {
        if (!$this->batchManager->markJobProcessed($message->getBatchId())) {
            return;
        }

        $this->messageBus->dispatch($message->createFinalizeMessage());
    }
}
