<?php

namespace App\Tests\Integration\Search\Product\Infrastructure;

use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Entity\PriceType;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\ProductSnapshot;
use App\Entity\Stores;
use App\Entity\StoresElementsStocks;
use App\Search\Product\Infrastructure\Messenger\ProductSearchOutboxEvent;
use App\Search\Product\Infrastructure\Messenger\ProductSearchOutboxEventHandler;
use App\Search\Product\Infrastructure\Messenger\ProductSearchReindexMessage;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class ProductSearchOutboxDoctrineListenerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
        }

        $schemaTool->createSchema($metadata);

        $outboxTransport = $this->outboxTransport();
        self::assertInstanceOf(SetupableTransportInterface::class, $outboxTransport);
        $outboxTransport->setup();
        $this->clearOutbox();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    public function testCreatingProductCreatesTransactionalOutboxEvent(): void
    {
        $element = $this->createElement("created-product");

        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());
    }

    public function testOutboxHandlerPublishesMinimalMessageToRabbitTransport(): void
    {
        $element = $this->createElement("relayed-product");
        $events = $this->receiveOutboxEvents();
        self::assertCount(1, $events);

        static::getContainer()->get(ProductSearchOutboxEventHandler::class)($events[0]);

        $transport = static::getContainer()->get("messenger.transport.catalog_search");
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertCount(1, $transport->getSent());

        $message = $transport->getSent()[0]->getMessage();
        self::assertInstanceOf(ProductSearchReindexMessage::class, $message);
        self::assertSame($element->getId(), $message->catalogElementId);
    }

    public function testOutboxEventRollsBackWithBusinessTransaction(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->createElement("rolled-back-product");
            self::assertSame(1, $this->outboxCount());
        } finally {
            $this->connection->rollBack();
        }

        self::assertSame(0, $this->outboxCount());
    }

    public function testOutboxEventRollsBackWithDoctrineImplicitFlushTransaction(): void
    {
        $element = $this->createElement("implicit-rollback-product");
        $this->clearOutbox();

        $element->setName("Changed before failed flush");
        $duplicate = (new CatalogElements())
            ->setName("Duplicate")
            ->setSlug("implicit-rollback-product")
            ->setActive(true)
            ->setCreatedBy(1)
            ->setCreatedAt(new \DateTimeImmutable("2026-09-01T00:00:00+00:00"));
        $this->entityManager->persist($duplicate);

        try {
            $this->entityManager->flush();
            self::fail("The duplicate product slug must make the flush fail.");
        } catch (UniqueConstraintViolationException) {
        }

        self::assertSame([], $this->outboxCatalogElementIds());
    }

    public function testSnapshotProductDoesNotCreateCatalogOutboxEvent(): void
    {
        $originalElement = $this->createElement("snapshot-source-product");
        $this->clearOutbox();

        $snapshotProduct = (new Product())
            ->setName("Snapshot")
            ->setSlug("snapshot-product")
            ->setActive(true)
            ->setCreatedBy(1)
            ->setCreatedAt(new \DateTimeImmutable("2026-09-01T00:00:00+00:00"));
        $snapshot = (new ProductSnapshot())
            ->setProduct($snapshotProduct)
            ->setOriginalProduct($originalElement);

        $this->entityManager->persist($snapshot);
        $this->entityManager->flush();

        self::assertSame([], $this->outboxCatalogElementIds());
    }

    public function testProductUpdateAndDeactivationCreateOutboxEvents(): void
    {
        $element = $this->createElement("updated-product");
        $this->clearOutbox();

        $element->setName("Updated name")->setActive(false);
        $this->entityManager->flush();

        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());
    }

    public function testPriceCreateUpdateAndDeleteCreateOutboxEvents(): void
    {
        $element = $this->createElement("priced-product");
        $priceType = $this->createPriceType();
        $price = (new ProductPrice())
            ->setProduct($element)
            ->setPriceType($priceType)
            ->setPrice(1000)
            ->setCurrency("RUB")
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->clearOutbox();
        $this->entityManager->persist($price);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());

        $this->clearOutbox();
        $price->setPrice(1200);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());

        $this->clearOutbox();
        $this->entityManager->remove($price);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());
    }

    public function testStockAndStoreChangesCreateOutboxEvents(): void
    {
        $element = $this->createElement("stocked-product");
        $store = (new Stores())->setName("Main store")->setSlug("main-store")->setActive(true);
        $stock = (new StoresElementsStocks())->setElement($element)->setStore($store)->setStock(5);

        $this->clearOutbox();
        $this->entityManager->persist($store);
        $this->entityManager->persist($stock);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());

        $this->clearOutbox();
        $stock->setStock(9);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());

        $this->clearOutbox();
        $store->setName("Renamed store");
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());

        $this->clearOutbox();
        $this->entityManager->remove($stock);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());
    }

    public function testCategoryRelationAndCategoryChangesCreateOutboxEvents(): void
    {
        $element = $this->createElement("categorized-product");
        $section = (new CatalogSections())
            ->setName("Section")
            ->setSlug("section")
            ->setActive(true)
            ->setSort(100);
        $this->entityManager->persist($section);
        $this->entityManager->flush();
        $this->clearOutbox();

        $element->addSection($section);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());

        $this->clearOutbox();
        $section->setName("Renamed section");
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());

        $this->clearOutbox();
        $element->removeSection($section);
        $this->entityManager->flush();
        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());
    }

    public function testPriceTypeChangeCreatesOutboxForAffectedProduct(): void
    {
        $element = $this->createElement("price-type-product");
        $priceType = $this->createPriceType();
        $price = (new ProductPrice())
            ->setProduct($element)
            ->setPriceType($priceType)
            ->setPrice(1000)
            ->setCurrency("RUB")
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($price);
        $this->entityManager->flush();
        $this->clearOutbox();

        $priceType->setName("Renamed price type");
        $this->entityManager->flush();

        self::assertSame([$element->getId()], $this->outboxCatalogElementIds());
    }

    public function testCatalogElementDeletionCreatesOutboxEvent(): void
    {
        $element = $this->createElement("deleted-product");
        $elementId = $element->getId();
        $this->clearOutbox();

        $this->entityManager->remove($element);
        $this->entityManager->flush();

        self::assertSame([$elementId], $this->outboxCatalogElementIds());
    }

    private function createElement(string $slug): CatalogElements
    {
        $element = (new CatalogElements())
            ->setName($slug)
            ->setSlug($slug)
            ->setActive(true)
            ->setCreatedBy(1)
            ->setCreatedAt(new \DateTimeImmutable("2026-09-01T00:00:00+00:00"));
        $this->entityManager->persist($element);
        $this->entityManager->flush();

        return $element;
    }

    private function createPriceType(): PriceType
    {
        $priceType = (new PriceType())
            ->setCode("BASE")
            ->setName("Base")
            ->setActive(true)
            ->setSort(100)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($priceType);
        $this->entityManager->flush();

        return $priceType;
    }

    /**
     * @return int[]
     */
    private function outboxCatalogElementIds(): array
    {
        return array_map(
            static fn (ProductSearchOutboxEvent $event): int => $event->catalogElementId,
            $this->receiveOutboxEvents(),
        );
    }

    private function clearOutbox(): void
    {
        $this->connection->executeStatement("DELETE FROM messenger_messages");
    }

    private function outboxCount(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queueName",
            ["queueName" => "catalog_search_outbox"],
        );
    }

    /**
     * @return ProductSearchOutboxEvent[]
     */
    private function receiveOutboxEvents(): array
    {
        $events = [];
        $transport = $this->outboxTransport();

        while (true) {
            $envelopes = [...$transport->get()];
            if ($envelopes === []) {
                break;
            }

            foreach ($envelopes as $envelope) {
                $message = $envelope->getMessage();
                self::assertInstanceOf(ProductSearchOutboxEvent::class, $message);
                $events[] = $message;
                $transport->ack($envelope);
            }
        }

        return $events;
    }

    private function outboxTransport(): TransportInterface
    {
        $transport = static::getContainer()->get("messenger.transport.catalog_search_outbox");
        self::assertInstanceOf(TransportInterface::class, $transport);

        return $transport;
    }
}
