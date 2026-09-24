<?php

namespace App\Tests\Integration\Repository;

use App\Entity\CatalogSections;
use App\Repository\CatalogSectionsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CatalogSectionsRepositoryCacheTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CatalogSectionsRepository $repository;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(CatalogSectionsRepository::class);
        $this->cache = new ArrayAdapter();
        $this->entityManager->getConfiguration()->setResultCache($this->cache);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
        }

        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    public function testListUsesResultCacheWithoutChangingActiveFilterOrSort(): void
    {
        $first = $this->createSection('first', true, 10);
        $second = $this->createSection('second', true, 20);
        $this->createSection('inactive', false, 30);

        self::assertSame(
            [$second->getId(), $first->getId()],
            array_map(static fn(CatalogSections $section): ?int => $section->getId(), $this->repository->findActiveForPublicList()),
        );

        $this->updateSectionName($second, 'changed');
        $this->entityManager->clear();

        self::assertSame('second', $this->repository->findActiveForPublicList()[0]->getName());

        $this->cache->clear();
        $this->entityManager->clear();

        self::assertSame('changed', $this->repository->findActiveForPublicList()[0]->getName());
    }

    public function testItemUsesResultCacheAndStillReturnsNullWhenMissing(): void
    {
        $section = $this->createSection('item', false, 10);
        $id = $section->getId();
        self::assertNotNull($id);
        self::assertNull($this->repository->findOneForPublicApi($id + 1000));
        self::assertSame('item', $this->repository->findOneForPublicApi($id)?->getName());

        $this->updateSectionName($section, 'changed');
        $this->entityManager->clear();

        self::assertSame('item', $this->repository->findOneForPublicApi($id)?->getName());

        $this->cache->clear();
        $this->entityManager->clear();

        self::assertSame('changed', $this->repository->findOneForPublicApi($id)?->getName());
    }

    public function testMissingItemResultIsCached(): void
    {
        $section = $this->createSection('item', true, 10);
        $id = $section->getId();
        self::assertNotNull($id);
        $missingId = $id + 1000;

        self::assertNull($this->repository->findOneForPublicApi($missingId));

        $table = $this->entityManager->getClassMetadata(CatalogSections::class)->getTableName();
        $this->entityManager->getConnection()->update($table, ['id' => $missingId], ['id' => $id]);
        $this->entityManager->clear();

        self::assertNull($this->repository->findOneForPublicApi($missingId));

        $this->cache->clear();

        self::assertSame($missingId, $this->repository->findOneForPublicApi($missingId)?->getId());
    }

    private function createSection(string $slug, bool $active, int $sort): CatalogSections
    {
        $section = (new CatalogSections())
            ->setName($slug)
            ->setSlug($slug)
            ->setActive($active)
            ->setSort($sort);

        $this->entityManager->persist($section);
        $this->entityManager->flush();

        return $section;
    }

    private function updateSectionName(CatalogSections $section, string $name): void
    {
        $table = $this->entityManager->getClassMetadata(CatalogSections::class)->getTableName();
        $this->entityManager->getConnection()->update($table, ['name' => $name], ['id' => $section->getId()]);
    }
}
