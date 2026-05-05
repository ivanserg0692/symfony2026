<?php

namespace App\News;

use App\Entity\News;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

final readonly class NewsExportCsvStorage
{
    private const array CSV_HEADER = [
        'id',
        'name',
        'slug',
        'status',
        'brief',
        'description',
        'created_at',
        'created_by',
    ];

    public function __construct(
        private FilesystemOperator $newsExportStorage,
    ) {
    }

    /**
     * @param list<News> $newsItems
     * @throws FilesystemException
     */
    public function writeChunk(int $newsExportId, array $newsItems): void
    {
        if ([] === $newsItems) {
            return;
        }

        $startNewsId = $this->getStartNewsId($newsItems);
        $stream = $this->createTemporaryStream();

        foreach ($newsItems as $news) {
            fputcsv($stream, $this->createNewsRow($news), ',', '"', '');
        }

        rewind($stream);
        $this->newsExportStorage->writeStream($this->createChunkPath($newsExportId, $startNewsId), $stream);
        fclose($stream);
    }

    /**
     * @param non-empty-list<News> $newsItems
     */
    private function getStartNewsId(array $newsItems): int
    {
        $startNewsId = $newsItems[0]->getId();

        if (null === $startNewsId) {
            throw new \LogicException('Cannot create news export chunk for news without id.');
        }

        return $startNewsId;
    }

    public function finalize(int $newsExportId): string
    {
        $chunkPaths = $this->findChunkPaths($newsExportId);
        $finalPath = $this->createFinalPath($newsExportId);
        $stream = $this->createTemporaryStream();

        fputcsv($stream, self::CSV_HEADER, ',', '"', '');

        foreach ($chunkPaths as $chunkPath) {
            $chunkStream = $this->newsExportStorage->readStream($chunkPath);

            if (!\is_resource($chunkStream)) {
                throw new \RuntimeException(sprintf('Cannot read news export chunk "%s".', $chunkPath));
            }

            stream_copy_to_stream($chunkStream, $stream);
            fclose($chunkStream);
        }

        rewind($stream);
        $this->newsExportStorage->writeStream($finalPath, $stream);
        fclose($stream);

        foreach ($chunkPaths as $chunkPath) {
            $this->newsExportStorage->delete($chunkPath);
        }

        return $finalPath;
    }

    public function deleteChunks(int $newsExportId): void
    {
        foreach ($this->findChunkPaths($newsExportId) as $chunkPath) {
            $this->newsExportStorage->delete($chunkPath);
        }
    }

    private function createChunkPath(int $newsExportId, int $startNewsId): string
    {
        return sprintf('news-exports/%d/chunks/%012d.csv', $newsExportId, $startNewsId);
    }

    private function createFinalPath(int $newsExportId): string
    {
        return sprintf('news-exports/%d/news-export-%d.csv', $newsExportId, $newsExportId);
    }

    /**
     * @return list<string>
     */
    private function findChunkPaths(int $newsExportId): array
    {
        $chunkPaths = [];

        foreach ($this->newsExportStorage->listContents(sprintf('news-exports/%d/chunks', $newsExportId)) as $item) {
            if (!$item->isFile() || !str_ends_with($item->path(), '.csv')) {
                continue;
            }

            $chunkPaths[] = $item->path();
        }

        sort($chunkPaths, SORT_STRING);

        return $chunkPaths;
    }

    /**
     * @return resource
     */
    private function createTemporaryStream()
    {
        $stream = fopen('php://temp', 'w+b');

        if (false === $stream) {
            throw new \RuntimeException('Cannot create temporary stream for news export.');
        }

        return $stream;
    }

    /**
     * @return list<string|int|null>
     */
    private function createNewsRow(News $news): array
    {
        return [
            $news->getId(),
            $news->getName(),
            $news->getSlug(),
            $news->getStatus()?->getCode()?->value,
            $news->getBrief(),
            $news->getDescription(),
            $news->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            $news->getCreatedBy()?->getUserIdentifier(),
        ];
    }
}
