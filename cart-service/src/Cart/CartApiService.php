<?php

namespace App\Cart;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CartApiService
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function findCurrentCart(int $ownerId): ?Cart
    {
        return $this->cartRepository->findActiveForOwnerWithItems($ownerId);
    }

    public function updateItem(int $itemId, int $ownerId, string $payload): ?CartItem
    {
        $item = $this->cartItemRepository->findOneInActiveCartForOwner($itemId, $ownerId);

        if ($item === null) {
            return null;
        }

        $updateRequest = $this->deserializeUpdateRequest($payload);

        if ($updateRequest->hasQuantity() && $updateRequest->getQuantity() !== null) {
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

    private function deserializeUpdateRequest(string $payload): CartItemUpdateRequest
    {
        if (trim($payload) === "") {
            throw new \InvalidArgumentException("Request body must contain valid JSON.");
        }

        try {
            $updateRequest = $this->serializer->deserialize($payload, CartItemUpdateRequest::class, "json", [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ]);
        } catch (SerializerExceptionInterface $exception) {
            throw new \InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        if (!$updateRequest instanceof CartItemUpdateRequest) {
            throw new \InvalidArgumentException("Request body must contain quantity or sort.");
        }

        $violations = $this->validator->validate($updateRequest);

        if (count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations);
        }

        return $updateRequest;
    }
}
