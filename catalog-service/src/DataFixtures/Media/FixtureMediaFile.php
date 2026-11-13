<?php

namespace App\DataFixtures\Media;

final readonly class FixtureMediaFile
{
    public function __construct(
        public string $localPath,
        public string $key,
        public string $sha256,
        public string $contentType,
    ) {
        if (!preg_match('/^[a-f0-9]{64}$/D', $sha256)
            || str_contains($localPath, '..') || str_starts_with($localPath, '/')
            || !preg_match('~^catalog/demo/v1/[a-z0-9-]+/[a-f0-9]{16}/(?:source\.(?:jpg|png)|thumb\.webp|card\.webp|main\.webp)$~D', $key)) {
            throw new \InvalidArgumentException('Invalid catalog media file metadata.');
        }
    }
}
