<?php

namespace App\Order;

use App\Entity\Order;
use App\Repository\OrderRepository;

class OrderApiService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
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
}
