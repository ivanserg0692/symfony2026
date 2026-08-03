<?php

namespace App\Cart;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CartItemUpdateRequest
{
    private bool $quantityProvided = false;

    private bool $sortProvided = false;

    #[Assert\Type("integer")]
    #[Assert\Positive]
    private ?int $quantity = null;

    #[Assert\Type("integer")]
    private ?int $sort = null;

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantityProvided = true;
        $this->quantity = $quantity;
    }

    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function setSort(?int $sort): void
    {
        $this->sortProvided = true;
        $this->sort = $sort;
    }

    public function hasQuantity(): bool
    {
        return $this->quantityProvided;
    }

    public function hasSort(): bool
    {
        return $this->sortProvided;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (!$this->quantityProvided && !$this->sortProvided) {
            $context->buildViolation("Request body must contain quantity or sort.")
                ->addViolation();
        }

        if ($this->quantityProvided && $this->quantity === null) {
            $context->buildViolation("Quantity must be a positive integer.")
                ->atPath("quantity")
                ->addViolation();
        }

        if ($this->sortProvided && $this->sort === null) {
            $context->buildViolation("Sort must be an integer.")
                ->atPath("sort")
                ->addViolation();
        }
    }
}
