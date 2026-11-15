<?php

namespace App\Tests\Support;

use App\Entity\CatalogMedia;
use App\Media\CatalogMediaVariant;

final class CatalogMediaFactory
{
    public static function key(string $name): string
    {
        return str_starts_with($name, 'catalog/') ? $name : 'catalog/demo/v1/'.$name.'/0123456789abcdef/main.webp';
    }

    public static function create(string $name = 'chair', string $alt = 'Chair'): CatalogMedia
    {
        $key = self::key($name);
        $variant = static fn (string $size): CatalogMediaVariant => new CatalogMediaVariant(dirname($key).'/'.$size.'.webp', str_repeat('a', 64));
        return new CatalogMedia($key, $variant('thumb'), $variant('card'), $variant('main'),
            $alt, 'Author', 'https://example.com/source', 'CC BY 4.0', 'https://example.com/license', 'Credit');
    }
}
