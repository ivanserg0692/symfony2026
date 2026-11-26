<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
#[ORM\Entity]
#[ORM\Table(name: 'guest_cart_transfers')]
class GuestCartTransfer
{
    /** @param array{transferred: list<int>, unavailable: list<int>} $result */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $ownerId,
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $operationId,
        #[ORM\Column(length: 64)]
        private string $payloadHash,
        #[ORM\Column(type: 'json')]
        private array $result,
    ) {
    }

    /** @return array{transferred: list<int>, unavailable: list<int>} */
    public function getResultFor(string $payloadHash): array
    {
        if ($this->payloadHash !== $payloadHash) {
            throw new \DomainException('This operation ID has already been used with different transfer data.');
        }

        return $this->result;
    }
}
