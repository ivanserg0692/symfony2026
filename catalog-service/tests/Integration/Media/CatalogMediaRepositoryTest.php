<?php

namespace App\Tests\Integration\Media;

use App\Media\Infrastructure\CatalogPictureResponseFactory;
use App\Search\Product\Infrastructure\CatalogResponseFactory;
use App\Search\Product\Application\ProductSearchDocumentBuilder;
use App\DataFixtures\Media\CatalogMediaBulkWriter;
use App\DataFixtures\ProductSnapshotFixtures;
use App\Entity\CatalogElements;
use App\Entity\CatalogMedia;
use App\Entity\CatalogSections;
use App\Entity\Product;
use App\Media\CatalogMediaVariant;
use App\Repository\CatalogElementsRepository;
use App\Repository\CatalogMediaRepository;
use App\Repository\CatalogSectionsRepository;
use App\RoadRunner\Grpc\GrpcHandlerTiming;
use App\Controller\Api\CatalogSectionsController;
use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Doctrine\DoctrineCatalogReader;
use App\Repository\ProductSnapshotRepository;
use App\Tests\Support\CatalogMediaFactory;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Schema\Schema;
use Psr\Log\NullLogger;
use Psr\Log\AbstractLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogMediaRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('_test', $this->entityManager->getConnection()->getDatabase());
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->entityManager);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    public function testSharedFileHasIndependentRowsAndOwnerMetadata(): void
    {
        $first = $this->element('first', 'First alt');
        $second = $this->element('second', 'Second alt');
        $this->entityManager->flush();
        $firstId = $first->getProduct()->getId();
        $secondId = $second->getProduct()->getId();
        $firstMediaId = $first->getProduct()->getPicture()->getId();
        self::assertNotSame($firstMediaId, $second->getProduct()->getPicture()->getId());
        self::assertSame($first->getPictureKey(), $second->getPictureKey());
        $this->entityManager->clear();
        $products = $this->entityManager->createQueryBuilder()->select('p', 'm')->from(Product::class, 'p')
            ->leftJoin('p.picture', 'm')->where('p.id IN (:ids)')->setParameter('ids', [$firstId, $secondId])
            ->orderBy('p.id')->getQuery()->getResult();
        self::assertSame('First alt', $products[0]->getPicture()->getAlt());
        self::assertSame('Second alt', $products[1]->getPicture()->getAlt());
        $loaded = static::getContainer()->get(CatalogElementsRepository::class)->findOneForPublicApi($first->getId());
        $this->entityManager->remove($loaded->getProduct());
        $this->entityManager->flush();
        self::assertNull($this->entityManager->find(CatalogMedia::class, $firstMediaId));
        self::assertNotNull(static::getContainer()->get(CatalogMediaRepository::class)->findOneByVariantKey($second->getPictureKey()));
    }

    public function testDatabaseRejectsASecondProductUsingTheSameMediaRow(): void
    {
        $first = $this->element('first', 'Alt');
        $second = $this->element('second', 'Alt');
        $this->entityManager->flush();
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->getConnection()->executeStatement('UPDATE product SET picture_id = ? WHERE id = ?', [
            $first->getProduct()->getPicture()->getId(), $second->getProduct()->getId(),
        ]);
    }

    public function testOpaqueVariantKeysResolveThroughOrmAndRouter(): void
    {
        $picture = new CatalogMedia(
            new CatalogMediaVariant('catalog/previews/small.webp', str_repeat('a', 64)),
            new CatalogMediaVariant('catalog/products/2026/medium.webp', str_repeat('b', 64)),
            new CatalogMediaVariant('catalog/original-sized.webp', str_repeat('c', 64)),
            'Alt', 'Author', 'https://example.com', 'License', 'https://example.com/license', 'Credit',
        );
        $this->entityManager->persist($picture);
        $this->entityManager->persist($picture->copy());
        $this->entityManager->flush();
        $this->entityManager->clear();
        $repository = static::getContainer()->get(CatalogMediaRepository::class);
        $router = static::getContainer()->get('router');
        foreach (['thumb', 'card', 'main'] as $variant) {
            $file = $picture->getVariant($variant);
            $loaded = $repository->findOneByVariantKey($file->key);
            self::assertNotNull($loaded);
            self::assertSame($file->sha256, $loaded->getVariantByKey($file->key)->sha256);
            $route = $router->match('/api/catalog/media/'.$file->key);
            self::assertSame('api_catalog_media', $route['_route']);
            self::assertSame($file->key, $route['key']);
        }
        self::assertNull($repository->findOneByVariantKey("missing.webp' OR 1=1 --"));
        self::assertNull($picture->getVariantByKey('private/source.jpg'));
        $columns = $this->entityManager->getConnection()->createSchemaManager()->listTableColumns('catalog_media');
        self::assertArrayNotHasKey('variants', $columns);
        self::assertArrayNotHasKey('picture_id', $columns);
        $indexes = $this->entityManager->getConnection()->fetchAllKeyValue(
            'SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = :table',
            ['table' => 'catalog_media'],
        );
        foreach (['thumbnail', 'card', 'main'] as $name) {
            self::assertStringContainsString(' USING btree ('.$name.'_key)', $indexes['catalog_media_'.$name.'_idx']);
            self::assertStringNotContainsString('CREATE UNIQUE INDEX', $indexes['catalog_media_'.$name.'_idx']);
        }
    }

    public function testConsolidatedMigrationCanRollBackAndRecreateTheMediaSchema(): void
    {
        require_once dirname(__DIR__, 3).'/migrations/Version20261114173605.php';
        $connection = $this->entityManager->getConnection();
        $migration = new \DoctrineMigrations\Version20261114173605($connection, new NullLogger());
        $migration->down(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertFalse($connection->createSchemaManager()->tablesExist(['catalog_media']));
        $migration = new \DoctrineMigrations\Version20261114173605($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        $schemaChanges = (new SchemaTool($this->entityManager))->getUpdateSchemaSql($this->entityManager->getMetadataFactory()->getAllMetadata());
        // Doctrine's migration version table is infrastructure, outside the ORM mapping.
        self::assertSame([], array_values(array_filter($schemaChanges,
            static fn (string $sql): bool => $sql !== 'DROP TABLE doctrine_migration_versions',
        )));
    }

    public function testOrmSnapshotCopiesMetadataIntoANewRowAndSharesOnlyFiles(): void
    {
        $element = $this->element('original', 'Original alt');
        $this->entityManager->flush();
        $snapshot = static::getContainer()->get(ProductSnapshotRepository::class)->createFromCatalogElement($element, 'test');
        $this->entityManager->flush();
        $snapshotMediaId = $snapshot->getProduct()->getPicture()->getId();
        self::assertNotSame($element->getProduct()->getPicture()->getId(), $snapshotMediaId);
        self::assertSame($element->getPictureKey(), $snapshot->getProduct()->getPictureKey());
        $element->setPicture(CatalogMediaFactory::create('chair', 'Replacement alt'));
        $this->entityManager->flush();
        $this->entityManager->clear();
        self::assertSame('Original alt', $this->entityManager->find(CatalogMedia::class, $snapshotMediaId)->getAlt());
    }

    public function testBulkMediaAndSnapshotFixtureAreBoundedAndIdempotent(): void
    {
        $first = $this->element('first', 'First');
        $second = $this->element('second', 'Second');
        $this->entityManager->flush();
        $connection = $this->entityManager->getConnection();
        $rows = array_fill(0, 1000, CatalogMediaBulkWriter::row(CatalogMediaFactory::create()));
        $ids = CatalogMediaBulkWriter::insert($connection, $rows);
        self::assertCount(1000, array_unique($ids));
        $fixture = new ProductSnapshotFixtures();
        $method = new \ReflectionMethod($fixture, 'loadProductSnapshots');
        $method->invoke($fixture, $connection, 2, 1);
        $method->invoke($fixture, $connection, 2, 1);
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_snapshot'));
        self::assertSame(1004, (int) $connection->fetchOne('SELECT COUNT(*) FROM catalog_media'));
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(DISTINCT picture_id) FROM product'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(DISTINCT main_key) FROM catalog_media'));
        self::assertSame(['First', 'Second'], $connection->fetchFirstColumn('SELECT media.alt FROM product_snapshot s JOIN product p ON p.id = s.product_id JOIN catalog_media media ON media.id = p.picture_id ORDER BY s.original_product_id'));
    }

    /** @dataProvider fixtureModes */
    public function testProductFixturesCreateMediaPerOwner(string $method): void
    {
        $fixture = new \App\DataFixtures\AppFixtures(new \App\DataFixtures\Media\FixtureMediaLibrary(dirname(__DIR__, 3).'/fixtures/media'));
        $leafSections = [];
        $sectionMethod = new \ReflectionMethod($fixture, 'loadCatalogSections');
        $sectionMethod->invokeArgs($fixture, [$this->entityManager, ['Catalog' => ['Home Garden' => ['Furniture' => ['Office' => []]]]], null, [], &$leafSections]);
        $stores = (new \ReflectionMethod($fixture, 'loadStores'))->invoke($fixture, $this->entityManager);
        $prices = (new \ReflectionMethod($fixture, 'loadPriceTypes'))->invoke($fixture, $this->entityManager);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $faker = \Faker\Factory::create('en_US');
        $faker->seed(20260705);
        (new \ReflectionMethod($fixture, $method))->invoke($fixture,
            $method === 'loadProductsWithOrm' ? $this->entityManager : $this->entityManager->getConnection(),
            $faker, $leafSections, $stores, $prices, 4,
        );
        $connection = $this->entityManager->getConnection();
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(DISTINCT picture_id) FROM product'));
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(DISTINCT picture_id) FROM catalog_sections'));
        self::assertSame(8, (int) $connection->fetchOne('SELECT COUNT(*) FROM catalog_media'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM product p JOIN catalog_sections s ON s.picture_id = p.picture_id'));
        self::assertGreaterThan(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_price'));
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(DISTINCT product_id) FROM product_catalog_sections'));
    }

    public static function fixtureModes(): iterable
    {
        yield ['loadProductsWithOrm'];
        yield ['loadProductsWithBulkInsert'];
    }

    public function testMediaQueriesDoNotGrowWithTheNumberOfProducts(): void
    {
        $ids = [];
        for ($i = 1; $i <= 30; ++$i) {
            $element = $this->element('product-'.$i, 'Alt '.$i);
            $section = (new CatalogSections())->setName('Section '.$i)->setSlug('section-'.$i)->setActive(true)
                ->setPicture(CatalogMediaFactory::create('chair', 'Section '.$i));
            $this->entityManager->persist($section);
            $element->addSection($section);
            $ids[] = $element;
        }
        $this->entityManager->flush();
        $productIds = array_map(static fn ($element) => $element->getProduct()->getId(), $ids);
        $counter = new class extends AbstractLogger {
            public int $queries = 0;
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (isset($context['sql'])) {
                    ++$this->queries;
                }
            }
        };
        $configuration = new Configuration();
        $configuration->setMiddlewares([new Middleware($counter)]);
        $connection = DriverManager::getConnection($this->entityManager->getConnection()->getParams(), $configuration);
        $manager = new EntityManager($connection, $this->entityManager->getConfiguration());
        $registry = $this->createMock(\Doctrine\Persistence\ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $reader = new DoctrineCatalogReader(new CatalogElementsRepository($registry, static::getContainer()->get(GrpcHandlerTiming::class)), new CatalogResponseFactory(new CatalogPictureResponseFactory('https://example.com/media')));
        $sections = new CatalogSectionsRepository($registry, 60);
        $sectionController = new CatalogSectionsController(new CatalogResponseFactory(new CatalogPictureResponseFactory('https://example.com/media')));
        $sectionController->setContainer(static::getContainer());
        try {
            foreach ([1, 30] as $count) {
                $manager->clear();
                $counter->queries = 0;
                $products = $manager->createQueryBuilder()->select('p', 'm')->from(Product::class, 'p')
                    ->leftJoin('p.picture', 'm')->where('p.id IN (:ids)')->setParameter('ids', array_slice($productIds, 0, $count))->getQuery()->getResult();
                foreach ($products as $product) {
                    self::assertNotNull($product->getPictureKey());
                    self::assertSame('Author', $product->getPicture()->getCreator());
                }
                self::assertSame(1, $counter->queries);
                $manager->clear();
                $counter->queries = 0;
                $page = $reader->findPage(new CatalogListCriteria(null, null, 1, $count, true));
                self::assertCount($count, $page->items);
                self::assertSame('Alt 1', $page->items[0]->picture->alt);
                self::assertSame('Author', $page->items[$count - 1]->picture->creator);
                self::assertSame(2, $counter->queries, 'ORM pages only select IDs and fetch joined entities.');

                $manager->clear();
                $counter->queries = 0;
                $item = $reader->findById($ids[0]->getId());
                self::assertSame('Alt 1', $item->picture->alt);
                self::assertSame('Section 1', $item->sections[0]->picture->alt);
                self::assertSame(1, $counter->queries, 'Detail pictures use the existing fetch joins.');

                $manager->clear();
                $counter->queries = 0;
                $indexElements = (new CatalogElementsRepository($registry, static::getContainer()->get(GrpcHandlerTiming::class)))
                    ->findForSearchIndexingByIds(array_map(static fn ($element) => $element->getId(), array_slice($ids, 0, $count)));
                foreach ($indexElements as $element) {
                    $document = (new ProductSearchDocumentBuilder())->build($element)->toArray();
                    self::assertSame('Author', $document['picture']['creator']);
                    self::assertSame('Author', $document['sections'][0]['picture']['creator']);
                }
                self::assertSame(3, $counter->queries, 'Indexing pictures uses the existing three bounded queries.');
            }
            $manager->clear();
            $manager->getConfiguration()->getResultCache()?->clear();
            $counter->queries = 0;
            $sectionResponses = json_decode($sectionController->list($sections)->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertCount(30, $sectionResponses);
            self::assertSame('Section 1', $sectionResponses[0]['picture']['alt']);
            self::assertSame('Section 30', $sectionResponses[29]['picture']['alt']);
            self::assertSame(1, $counter->queries, 'Section list pictures require no metadata query.');

            $manager->clear();
            $manager->getConfiguration()->getResultCache()?->clear();
            $counter->queries = 0;
            $sectionResponse = json_decode($sectionController->item($sectionResponses[0]['id'], $sections)->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('Section 1', $sectionResponse['picture']['alt']);
            self::assertSame(1, $counter->queries, 'Section detail pictures require no metadata query.');
        } finally {
            $manager->close();
            $connection->close();
        }
    }

    private function element(string $slug, string $alt): CatalogElements
    {
        $element = (new CatalogElements())->setName('Chair')->setSlug($slug)->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable())->setCreatedBy(1)->setSort(100)
            ->setPicture(CatalogMediaFactory::create('chair', $alt));
        $this->entityManager->persist($element);
        return $element;
    }
}
