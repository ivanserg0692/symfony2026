<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class ProductSnapshotFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
{
    private const PRODUCT_SNAPSHOT_COUNT = 1000000;
    private const BATCH_SIZE = 1000;

    public function getOrder(): int
    {
        return 20;
    }

    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('Expected %s.', EntityManagerInterface::class));
        }

        $this->loadProductSnapshots($manager->getConnection(), self::PRODUCT_SNAPSHOT_COUNT, self::BATCH_SIZE);
    }

    /**
     * @return array<int, string>
     */
    public static function getGroups(): array
    {
        return [
            'product_snapshots',
        ];
    }

    private function loadProductSnapshots(Connection $connection, int $snapshotCount, int $batchSize): void
    {
        if ($snapshotCount <= 0 || $batchSize <= 0) {
            return;
        }

        $createdSnapshots = 0;

        while ($createdSnapshots < $snapshotCount) {
            $sourceProducts = $this->findSourceProducts(
                $connection,
                min($batchSize, $snapshotCount - $createdSnapshots),
            );

            if ($sourceProducts === []) {
                return;
            }

            $connection->transactional(function () use ($connection, $sourceProducts): void {
                $snapshotProductRows = [];

                foreach ($sourceProducts as $sourceProduct) {
                    $originalProductId = (int) $sourceProduct['original_product_id'];

                    $snapshotProductRows[] = [
                        'name' => $sourceProduct['name'],
                        'created_at' => $sourceProduct['created_at'],
                        'active' => (bool) $sourceProduct['active'] ? 1 : 0,
                        'created_by' => $sourceProduct['created_by'],
                        'description' => $sourceProduct['description'],
                        'slug' => $this->createSnapshotSlug((string) $sourceProduct['slug'], $originalProductId),
                        'picture_id' => $sourceProduct['picture_id'],
                    ];
                }

                $snapshotProductIds = $this->insertRowsReturningIds(
                    $connection,
                    'product',
                    ['name', 'created_at', 'active', 'created_by', 'description', 'slug', 'picture_id'],
                    $snapshotProductRows,
                );
                $snapshotRows = [];

                foreach ($sourceProducts as $offset => $sourceProduct) {
                    $snapshotRows[] = [
                        'product_id' => $snapshotProductIds[$offset],
                        'original_product_id' => (int) $sourceProduct['original_product_id'],
                    ];
                }

                $this->insertRows($connection, 'product_snapshot', ['product_id', 'original_product_id'], $snapshotRows);
            });

            $createdSnapshots += \count($sourceProducts);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findSourceProducts(Connection $connection, int $limit): array
    {
        return $connection->executeQuery(
            <<<'SQL'
SELECT
    element.id AS original_product_id,
    product.name,
    product.created_at,
    product.active,
    product.created_by,
    product.description,
    product.slug,
    product.picture_id
FROM catalog_elements element
INNER JOIN product ON product.id = element.product_id
LEFT JOIN product_snapshot snapshot ON snapshot.original_product_id = element.id
WHERE snapshot.id IS NULL
ORDER BY element.id ASC
LIMIT ?
SQL,
            [$limit],
        )->fetchAllAssociative();
    }

    /**
     * @param array<int, string>               $columns
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, int>
     */
    private function insertRowsReturningIds(Connection $connection, string $table, array $columns, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        [$valuesSql, $parameters] = $this->createBulkValuesSql($columns, $rows);
        $sql = sprintf('INSERT INTO %s (%s) VALUES %s RETURNING id', $table, implode(', ', $columns), $valuesSql);

        return array_map('intval', $connection->executeQuery($sql, $parameters)->fetchFirstColumn());
    }

    /**
     * @param array<int, string>               $columns
     * @param array<int, array<string, mixed>> $rows
     */
    private function insertRows(Connection $connection, string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        [$valuesSql, $parameters] = $this->createBulkValuesSql($columns, $rows);
        $sql = sprintf('INSERT INTO %s (%s) VALUES %s', $table, implode(', ', $columns), $valuesSql);

        $connection->executeStatement($sql, $parameters);
    }

    /**
     * @param array<int, string>               $columns
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function createBulkValuesSql(array $columns, array $rows): array
    {
        $rowPlaceholder = sprintf('(%s)', implode(', ', array_fill(0, \count($columns), '?')));
        $valuesSql = implode(', ', array_fill(0, \count($rows), $rowPlaceholder));
        $parameters = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $parameters[] = $row[$column];
            }
        }

        return [$valuesSql, $parameters];
    }

    private function createSnapshotSlug(string $sourceSlug, int $originalProductId): string
    {
        $suffix = sprintf('-snapshot-%d', $originalProductId);

        return substr($sourceSlug, 0, 255 - \strlen($suffix)) . $suffix;
    }
}
