<?php

namespace App\Media;

final readonly class CatalogMediaVariant
{
    public function __construct(public string $key, public string $sha256)
    {
        if (!preg_match('~^catalog/(?:[a-zA-Z0-9_-]+/)+[a-zA-Z0-9_-]+\.webp$~D', $key)
            || !preg_match('/^[a-f0-9]{64}$/D', $sha256)) {
            throw new \InvalidArgumentException('Invalid catalog media variant.');
        }
    }
}
