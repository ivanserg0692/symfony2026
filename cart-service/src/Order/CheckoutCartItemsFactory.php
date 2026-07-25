<?php

namespace App\Order;

use App\Entity\CartItem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CheckoutCartItemsFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param iterable<CartItem> $items
     */
    public function createFromCartItems(iterable $items): CheckoutCartItems
    {
        $checkoutItems = [];

        foreach ($items as $item) {
            $checkoutItem = new CheckoutCartItem(
                (int) $item->getCatalogElementId(),
                (int) $item->getQuantity(),
                (int) $item->getSort(),
            );
            $violations = $this->validator->validate($checkoutItem);

            if (count($violations) > 0) {
                throw new InvalidCheckoutItemException((string) $violations);
            }

            $checkoutItems[] = $checkoutItem;
        }

        return new CheckoutCartItems($checkoutItems);
    }
}
