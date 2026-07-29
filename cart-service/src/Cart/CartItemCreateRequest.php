<?php

namespace App\Cart;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CartItemCreateRequest
{
    private bool $productIdProvided = false;

    private bool $quantityProvided = false;

    #[Assert\Type("integer")]
    #[Assert\Positive]
    private ?int $productId = null;

    #[Assert\Type("integer")]
    #[Assert\Positive]
    private ?int $quantity = null;

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): void
    {
        $this->productIdProvided = true;
        $this->productId = $productId;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantityProvided = true;
        $this->quantity = $quantity;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (!$this->productIdProvided || $this->productId === null) {
            $context->buildViolation("Product id must be a positive integer.")
                ->atPath("productId")
                ->addViolation();
        }

        if (!$this->quantityProvided || $this->quantity === null) {
            $context->buildViolation("Quantity must be a positive integer.")
                ->atPath("quantity")
                ->addViolation();
        }
    }
}
