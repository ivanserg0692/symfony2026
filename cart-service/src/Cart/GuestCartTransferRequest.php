<?php

namespace App\Cart;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Exclude]
final readonly class GuestCartTransferRequest
{
    public function __construct(
        #[Assert\Uuid]
        public string $operationId,
        #[Assert\Positive]
        public int $ownerId,
        #[Assert\Choice(choices: ['merge', 'replace'])]
        public string $mode,
        /** @var GuestCartTransferItem[] */
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public array $items,
    ) {
    }

    #[Assert\Callback]
    public function validateUniqueProducts(ExecutionContextInterface $context): void
    {
        $products = [];
        foreach ($this->items as $index => $item) {
            if (isset($products[$item->productId])) {
                $context->buildViolation('Each product ID must appear only once.')
                    ->atPath("items[$index].productId")
                    ->addViolation();
            }
            $products[$item->productId] = true;
        }
    }
}
