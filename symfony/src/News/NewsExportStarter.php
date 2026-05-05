<?php

namespace App\News;

use App\Entity\NewsExport;
use App\MessengerBatch\MessengerBatchManager;
use App\News\Message\ExportNewsMessage;
use App\Repository\NewsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class NewsExportStarter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessengerBatchManager $batchManager,
        private MessageBusInterface $messageBus,
        private NewsRepository $newsRepository,
    ) {
    }

    /**
     * @param list<int> $newsIds
     */
    public function start(array $newsIds = []): NewsExport
    {
        $newsIds = [] === $newsIds ? $this->findNewsIds() : $this->normalizeNewsIds($newsIds);

        if ([] === $newsIds) {
            throw new \RuntimeException('admin.news_export.error.empty');
        }

        $batch = $this->batchManager->create(\count($newsIds));
        $newsExport = new NewsExport($batch);

        $this->entityManager->persist($newsExport);
        $this->entityManager->flush();

        $this->batchManager->start((int) $batch->getId());

        foreach ($newsIds as $newsId) {
            $this->messageBus->dispatch(new ExportNewsMessage((int) $batch->getId(), $newsId));
        }

        return $newsExport;
    }

    /**
     * @return list<int>
     */
    private function findNewsIds(): array
    {
        $rows = $this->newsRepository
            ->createQueryBuilder('news')
            ->select('news.id')
            ->orderBy('news.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param list<int> $newsIds
     *
     * @return list<int>
     */
    private function normalizeNewsIds(array $newsIds): array
    {
        $newsIds = array_values(array_unique(array_filter($newsIds, static fn (int $newsId): bool => $newsId > 0)));

        if ([] === $newsIds) {
            return [];
        }

        $rows = $this->newsRepository
            ->createQueryBuilder('news')
            ->select('news.id')
            ->andWhere('news.id IN (:newsIds)')
            ->setParameter('newsIds', $newsIds)
            ->orderBy('news.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
