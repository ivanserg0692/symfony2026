<?php

namespace App\Tests\Functional;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

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

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function requestJson(string $method, string $uri, ?int $ownerId = null, ?array $body = null): void
    {
        $server = ["CONTENT_TYPE" => "application/json"];

        if ($ownerId !== null) {
            $server["HTTP_X_USER_ID"] = (string) $ownerId;
        }

        $this->client->request($method, $uri, server: $server, content: $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function createCart(int $ownerId, int $itemCount = 2): Cart
    {
        $now = new \DateTimeImmutable("2026-01-01 10:00:00");
        $cart = (new Cart())
            ->setOwnerId($ownerId)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        for ($i = 1; $i <= $itemCount; $i++) {
            $cart->addItem(
                (new CartItem())
                    ->setCatalogElementId(1000 + $ownerId + $i)
                    ->setQuantity($i)
                    ->setSort($i * 100)
                    ->setCreatedAt($now)
                    ->setUpdatedAt($now)
            );
        }

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }

    protected function createOrder(int $ownerId, \DateTimeImmutable $createdAt, int $itemCount = 2): Order
    {
        $order = (new Order())
            ->setOwnerId($ownerId)
            ->setTotalPrice("20.00")
            ->setTotalDiscount("1.00")
            ->setFinalPrice("19.00")
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($createdAt);

        for ($i = 1; $i <= $itemCount; $i++) {
            $order->addItem(
                (new OrderItem())
                    ->setProductSnapshotId(2000 + $ownerId + $i)
                    ->setQuantity($i)
                    ->setSort($i * 100)
                    ->setUnitPrice("10.00")
                    ->setUnitDiscount("0.50")
                    ->setFinalUnitPrice("9.50")
                    ->setLineTotal((string) (9.50 * $i))
                    ->setCreatedAt($createdAt)
            );
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}
