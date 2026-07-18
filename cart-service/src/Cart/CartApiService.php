<?php

namespace App\Cart;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartApiService
{
    private const ALLOWED_PATCH_FIELDS = ["quantity", "sort"];

    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findCurrentCart(int $ownerId): ?Cart
    {
        return $this->cartRepository->findActiveForOwnerWithItems($ownerId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateItem(int $itemId, int $ownerId, array $payload): ?CartItem
    {
        $this->validateUpdatePayload($payload);

        $item = $this->cartItemRepository->findOneInActiveCartForOwner($itemId, $ownerId);

        if ($item === null) {
            return null;
        }

        if (array_key_exists("quantity", $payload)) {
            $item->setQuantity($payload["quantity"]);
        }

        if (array_key_exists("sort", $payload)) {
            $item->setSort($payload["sort"]);
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

    /**
     * @param array<string, mixed> $payload
     */
    private function validateUpdatePayload(array $payload): void
    {
        if ($payload === []) {
            throw new \InvalidArgumentException("Request body must contain quantity or sort.");
        }

        $unknownFields = array_diff(array_keys($payload), self::ALLOWED_PATCH_FIELDS);

        if ($unknownFields !== []) {
            throw new \InvalidArgumentException(sprintf(
                "Unsupported field: %s.",
                implode(", ", $unknownFields),
            ));
        }

        if (array_key_exists("quantity", $payload) && (!\is_int($payload["quantity"]) || $payload["quantity"] <= 0)) {
            throw new \InvalidArgumentException("Quantity must be a positive integer.");
        }

        if (array_key_exists("sort", $payload) && !\is_int($payload["sort"])) {
            throw new \InvalidArgumentException("Sort must be an integer.");
        }
    }
}
