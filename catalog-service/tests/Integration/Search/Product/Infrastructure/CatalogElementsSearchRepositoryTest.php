<?php

namespace App\Tests\Integration\Search\Product\Infrastructure;

use App\Entity\CatalogElements;
use App\Repository\CatalogElementsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogElementsSearchRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CatalogElementsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(CatalogElementsRepository::class);

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

    public function testFindsBoundedBatchUsingIdKeyset(): void
    {
        $ids = [];
        foreach (range(1, 5) as $number) {
            $element = (new CatalogElements())
                ->setName("Product {$number}")
                ->setSlug("product-{$number}")
                ->setActive(true)
                ->setCreatedBy(1)
                ->setCreatedAt(new \DateTimeImmutable("2026-09-01T00:00:00+00:00"));
            $this->entityManager->persist($element);
            $this->entityManager->flush();
            $ids[] = $element->getId();
        }

        self::assertSame(
            [$ids[2], $ids[3]],
            $this->repository->findSearchIndexIdsAfter($ids[1], 2),
        );
    }
}
