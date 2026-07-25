<?php

namespace App\Order;

use App\Entity\Order;
use App\Grpc\CatalogInventoryClient;
use App\Repository\CartRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderApiService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CatalogInventoryClient $catalogInventoryClient,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly OrderFactory $orderFactory,
        private readonly CheckoutCartItemsFactory $checkoutCartItemsFactory,
        private readonly CheckoutOrderItemSourceFactory $checkoutOrderItemSourceFactory,
    ) {
    }

    /**
     * @return array{items: list<Order>, pagination: array{page: int, limit: int, total: int, pages: int}}
     */
    public function listOrders(int $ownerId, int $page, int $limit): array
    {
        $ids = $this->orderRepository->findPageIdsForOwner($ownerId, $page, $limit);
        $total = $this->orderRepository->countForOwner($ownerId);

        return [
            "items" => $this->orderRepository->findListByIdsForOwner($ids, $ownerId),
            "pagination" => [
                "page" => $page,
                "limit" => $limit,
                "total" => $total,
                "pages" => (int) ceil($total / $limit),
            ],
        ];
    }

    public function findOrder(int $orderId, int $ownerId): ?Order
    {
        return $this->orderRepository->findOneForOwnerWithItems($orderId, $ownerId);
    }

    public function createOrderFromCurrentCart(int $ownerId): Order
    {
        $operationId = null;
        $stocksDeducted = false;

        $this->entityManager->getConnection()->beginTransaction();

        try {
            $cart = $this->cartRepository->findForOwnerForUpdate($ownerId);

            if ($cart === null) {
                throw new ActiveCartNotFoundException();
            }

            $items = $cart->getItems()->toArray();

            if ($items === []) {
                throw new EmptyCartException();
            }

            $checkoutItems = $this->checkoutCartItemsFactory->createFromCartItems($items);
            $productIds = $checkoutItems->getProductIds();
            $operationId = $this->createDeductStocksOperationId();
            $prices = $this->catalogInventoryClient->getProductPrices($productIds);
            $deductionResult = $this->catalogInventoryClient->deductStocks($operationId, $checkoutItems->toDeductStocksItems());
            $stocksDeducted = true;
            $orderItemSource = $this->checkoutOrderItemSourceFactory->create($checkoutItems, $prices, $deductionResult);
            $now = new \DateTimeImmutable();
            $order = $this->buildOrder($ownerId, $orderItemSource, $operationId, $now);

            $this->entityManager->persist($order);
            $this->entityManager->remove($cart);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            return $order;
        } catch (\Throwable $exception) {
            $this->entityManager->getConnection()->rollBack();

            if ($stocksDeducted) {
                // TODO: restore deducted stocks through Inventory gRPC when RestoreStocks exists.
                $this->logger->critical("Order persistence failed after stock deduction.", [
                    "operationId" => $operationId,
                    "exception" => $exception,
                ]);
            }

            throw $exception;
        }
    }

    public function cancelOrder(int $orderId, int $ownerId): ?Order
    {
        $canceledMessage = null;

        $this->entityManager->getConnection()->beginTransaction();

        try {
            $order = $this->orderRepository->findOneForOwnerForUpdate($orderId, $ownerId);

            if ($order === null) {
                $this->entityManager->getConnection()->rollBack();

                return null;
            }

            if ($order->getStatus() === OrderStatus::Canceled) {
                throw new OrderAlreadyCanceledException();
            }

            if (!$this->canCancel($order)) {
                throw new OrderCancellationNotAllowedException($order->getStatus());
            }

            $canceledAt = new \DateTimeImmutable();
            $order->setStatus(OrderStatus::Canceled);
            $order->setUpdatedAt($canceledAt);
            $canceledMessage = new OrderCanceledMessage(
                (int) $order->getId(),
                (int) $order->getOwnerId(),
                $canceledAt,
                $order->getOperationId(),
            );

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->getConnection()->rollBack();
            }

            throw $exception;
        }

        $this->messageBus->dispatch($canceledMessage);

        return $order;
    }

    private function createDeductStocksOperationId(): string
    {
        return sprintf("cart-checkout-%s", bin2hex(random_bytes(16)));
    }

    private function buildOrder(
        int $ownerId,
        CheckoutOrderItemSource $orderItemSource,
        string $operationId,
        \DateTimeImmutable $now,
    ): Order {
        $totalPriceMinorUnits = 0;
        $totalDiscountMinorUnits = 0;
        $finalPriceMinorUnits = 0;

        $order = $this->orderFactory->createPendingOrder($ownerId, $operationId, $now);

        foreach ($orderItemSource->getCartItems() as $item) {
            $productId = $item->productId;
            $quantity = $item->quantity;
            $price = $orderItemSource->getPriceForProduct($productId);
            $deduction = $orderItemSource->getDeductionForProduct($productId);
            $unitPriceMinorUnits = $price->unitPriceMinorUnits;
            $unitDiscountMinorUnits = $price->unitDiscountMinorUnits;
            $finalUnitPriceMinorUnits = $price->finalUnitPriceMinorUnits;
            $lineTotalMinorUnits = $finalUnitPriceMinorUnits * $quantity;

            $totalPriceMinorUnits += $unitPriceMinorUnits * $quantity;
            $totalDiscountMinorUnits += $unitDiscountMinorUnits * $quantity;
            $finalPriceMinorUnits += $lineTotalMinorUnits;

            $order->addItem($this->orderFactory->createItemFromCheckout(
                $item,
                $deduction,
                $unitPriceMinorUnits,
                $unitDiscountMinorUnits,
                $finalUnitPriceMinorUnits,
                $lineTotalMinorUnits,
                $now,
            ));
        }

        return $this->orderFactory->setTotalsFromMinorUnits(
            $order,
            $totalPriceMinorUnits,
            $totalDiscountMinorUnits,
            $finalPriceMinorUnits,
        );
    }

    private function canCancel(Order $order): bool
    {
        return $order->getStatus() === OrderStatus::Pending;
    }
}
