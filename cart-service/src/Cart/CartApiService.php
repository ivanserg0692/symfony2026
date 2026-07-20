<?php

namespace App\Cart;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Grpc\CatalogInventoryClient;
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

    public function updateItem(int $itemId, int $ownerId, CartItemUpdateRequest $updateRequest): ?CartItem
    {
        $item = $this->cartItemRepository->findOneInActiveCartForOwner($itemId, $ownerId);

        if ($item === null) {
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

        $item->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

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
}
