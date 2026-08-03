<?php

namespace App\Cart;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Grpc\CatalogInventoryClient;
use App\Grpc\CatalogStockResponse;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CartApiService
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CatalogInventoryClient $catalogInventoryClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function findCurrentCart(int $ownerId): ?Cart
    {
        return $this->cartRepository->findActiveForOwnerWithItems($ownerId);
    }

    public function addItem(int $ownerId, CartItemCreateRequest $createRequest): CartItemMutationResult
    {
        $productId = (int) $createRequest->getProductId();
        $quantity = (int) $createRequest->getQuantity();

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $cart = $this->cartRepository->findForOwnerForUpdate($ownerId);
            $items = [];

            if ($cart === null) {
                $cart = $this->createCart($ownerId);
                $this->entityManager->persist($cart);
            } else {
                $items = $this->cartItemRepository->findForCart($cart);
            }

            $item = $this->findItemByProductId($items, $productId);
            $resultingQuantity = $item === null ? $quantity : (int) $item->getQuantity() + $quantity;

            try {
                $response = $this->catalogInventoryClient->checkStock($productId, $resultingQuantity);
            } catch (\Throwable $exception) {
                throw new CatalogInventoryUnavailableException("Catalog inventory service is unavailable.", previous: $exception);
            }

            if ($this->isMissingProductResponse($response, $productId)) {
                throw new CatalogProductNotFoundException($productId);
            }

            if (!$response->available) {
                throw new CartItemUnavailableException($productId, $resultingQuantity, $response->totalAvailableQuantity);
            }

            $created = $item === null;

            if ($item === null) {
                $item = $this->createItem($productId, $resultingQuantity, $this->nextSort($items));
                $item->setCart($cart);
                $this->entityManager->persist($item);
            } else {
                $item->setQuantity($resultingQuantity);
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->getConnection()->rollBack();

            throw $exception;
        }

        return new CartItemMutationResult($item, $created);
    }

    public function updateItem(int $itemId, int $ownerId, CartItemUpdateRequest $updateRequest): ?CartItem
    {
        $this->entityManager->getConnection()->beginTransaction();
        try {
            $cart = $this->cartRepository->findForOwnerForUpdate($ownerId);

            if ($cart === null) {
                $this->entityManager->getConnection()->commit();

                return null;
            }

            $item = $this->cartItemRepository->findOneForCart($cart, $itemId);

            if ($item === null) {
                $this->entityManager->getConnection()->commit();

                return null;
            }

            if ($updateRequest->hasQuantity() && $updateRequest->getQuantity() !== null) {
                $response = $this->catalogInventoryClient->checkStock($item->getCatalogElementId(), $updateRequest->getQuantity());
                if (!$response->available) {
                    throw new \InvalidArgumentException($this->translator->trans("cart.item.quantity.exceeds_available"));
                }

                $item->setQuantity($updateRequest->getQuantity());
            }

            if ($updateRequest->hasSort() && $updateRequest->getSort() !== null) {
                $item->setSort($updateRequest->getSort());
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->getConnection()->rollBack();

            throw $exception;
        }

        return $item;
    }

    public function deleteItem(int $itemId, int $ownerId): bool
    {
        $item = $this->cartItemRepository->findOneInActiveCartForOwner($itemId, $ownerId);

        if ($item === null) {
            return false;
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();

        return true;
    }

    public function clearCart(int $ownerId): void
    {
        $cart = $this->cartRepository->findActiveForOwner($ownerId);

        if ($cart === null) {
            return;
        }

        $this->entityManager->remove($cart);
        $this->entityManager->flush();
    }

    private function isMissingProductResponse(CatalogStockResponse $response, int $productId): bool
    {
        return $response->productId !== $productId
            || (!$response->available && $response->totalAvailableQuantity === 0 && $response->stores === []);
    }

    private function createCart(int $ownerId): Cart
    {
        return (new Cart())
            ->setOwnerId($ownerId);
    }

    private function createItem(int $productId, int $quantity, int $sort): CartItem
    {
        return (new CartItem())
            ->setCatalogElementId($productId)
            ->setQuantity($quantity)
            ->setSort($sort);
    }

    /**
     * @param list<CartItem> $items
     */
    private function findItemByProductId(array $items, int $productId): ?CartItem
    {
        foreach ($items as $item) {
            if ($item->getCatalogElementId() === $productId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param list<CartItem> $items
     */
    private function nextSort(array $items): int
    {
        $maxSort = 0;
        foreach ($items as $item) {
            $maxSort = max($maxSort, (int) $item->getSort());
        }

        return $maxSort + 100;
    }
}
