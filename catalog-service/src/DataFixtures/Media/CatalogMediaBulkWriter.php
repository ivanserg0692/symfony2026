<?php

namespace App\DataFixtures\Media;

use App\Entity\CatalogMedia;
use Doctrine\DBAL\Connection;

/** Bounded DBAL batches for fixtures; runtime persistence uses ORM associations. */
final class CatalogMediaBulkWriter
{
    /** @return array<string, string> */
    public static function row(CatalogMedia $media): array
    {
        $variants = [];
        foreach (['thumb', 'card', 'main'] as $name) {
            $variant = $media->getVariant($name);
            $variants[$name] = ['key' => $variant->key, 'sha256' => $variant->sha256];
        }
        return [
            'picture_id' => $media->getPictureId(), 'variants' => json_encode($variants, JSON_THROW_ON_ERROR),
            'alt' => $media->getAlt(), 'creator' => $media->getCreator(), 'source_url' => $media->getSourceUrl(),
            'license' => $media->getLicense(), 'license_url' => $media->getLicenseUrl(), 'attribution' => $media->getAttribution(),
        ];
    }

    /** @param list<array<string, mixed>> $rows @return list<int> */
    public static function insert(Connection $connection, array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $columns = ['picture_id', 'variants', 'alt', 'creator', 'source_url', 'license', 'license_url', 'attribution'];
        $values = [];
        $parameters = [];
        foreach ($rows as $row) {
            $values[] = '('.implode(', ', array_fill(0, count($columns), '?')).')';
            foreach ($columns as $column) {
                $parameters[] = $row[$column];
            }
        }
        return array_map('intval', $connection->executeQuery(
            'INSERT INTO catalog_media ('.implode(', ', $columns).') VALUES '.implode(', ', $values).' RETURNING id',
            $parameters,
        )->fetchFirstColumn());
    }
}
