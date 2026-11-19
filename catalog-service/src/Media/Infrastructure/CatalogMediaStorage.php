<?php

namespace App\Media\Infrastructure;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

final readonly class CatalogMediaStorage
{
    public function __construct(
        private S3Client $catalogMediaS3,
        private string $catalogMediaBucket,
    ) {
    }

    public function ensureBucket(): void
    {
        if (!$this->catalogMediaS3->doesBucketExistV2($this->catalogMediaBucket)) {
            $this->catalogMediaS3->createBucket(['Bucket' => $this->catalogMediaBucket]);
        }
    }

    public function uploadOnce(string $key, string $absolutePath, string $sha256, string $contentType): bool
    {
        $bucket = $this->catalogMediaBucket;
        try {
            $head = $this->catalogMediaS3->headObject(['Bucket' => $bucket, 'Key' => $key]);
            if (($head['Metadata']['sha256'] ?? null) !== $sha256) {
                throw new \RuntimeException('An immutable catalog media object has a different checksum.');
            }
            return false;
        } catch (S3Exception $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }
        }
        $this->catalogMediaS3->putObject([
            'Bucket' => $bucket, 'Key' => $key, 'SourceFile' => $absolutePath,
            'ContentType' => $contentType, 'Metadata' => ['sha256' => $sha256],
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);
        return true;
    }

    /** @return resource */
    public function read(string $key)
    {
        $result = $this->catalogMediaS3->getObject(['Bucket' => $this->catalogMediaBucket, 'Key' => $key]);
        return $result['Body']->detach();
    }

    public function checkExists(string $key): void
    {
        $this->catalogMediaS3->headObject(['Bucket' => $this->catalogMediaBucket, 'Key' => $key]);
    }
}
