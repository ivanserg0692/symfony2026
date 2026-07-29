<?php

namespace App\Entity;

use App\Repository\InventoryOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryOperationRepository::class)]
#[ORM\Table(name: 'inventory_operations')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_operation_id', columns: ['operation_id'])]
class InventoryOperation
{
    public const TYPE_STOCK_DEDUCTION = "deduct_stocks";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $operationId = null;

    #[ORM\Column(length: 64)]
    private ?string $type = null;

    #[ORM\Column(length: 64)]
    private ?string $requestHash = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON)]
    private ?array $responsePayload = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @param array<string, mixed> $responsePayload
     */
    public function __construct(
        string $operationId,
        string $type,
        string $requestHash,
        array $responsePayload,
    ) {
        $this->operationId = $operationId;
        $this->type = $type;
        $this->requestHash = $requestHash;
        $this->responsePayload = $responsePayload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getRequestHash(): ?string
    {
        return $this->requestHash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponsePayload(): array
    {
        return $this->responsePayload ?? [];
    }
}
