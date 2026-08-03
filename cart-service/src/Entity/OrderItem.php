<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
#[ORM\UniqueConstraint(name: 'uniq_order_items_order_sort', columns: ['order_id', 'sort'])]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["order:item"])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(name: 'product_snapshot_id')]
    #[Groups(["order:item"])]
    private ?int $productSnapshotId = null;

    #[ORM\Column]
    #[Groups(["order:item"])]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Groups(["order:item"])]
    private ?int $sort = null;

    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(["order:item"])]
    private ?string $unitPrice = null;

    #[ORM\Column(name: 'unit_discount', type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(["order:item"])]
    private ?string $unitDiscount = null;

    #[ORM\Column(name: 'final_unit_price', type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(["order:item"])]
    private ?string $finalUnitPrice = null;

    #[ORM\Column(name: 'line_total', type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(["order:item"])]
    private ?string $lineTotal = null;

    #[ORM\Column]
    #[Groups(["order:item"])]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getProductSnapshotId(): ?int
    {
        return $this->productSnapshotId;
    }

    public function getRequiredProductSnapshotId(): int
    {
        if ($this->productSnapshotId === null || $this->productSnapshotId <= 0) {
            throw new \LogicException("Order item has an invalid product snapshot id.");
        }

        return $this->productSnapshotId;
    }

    public function setProductSnapshotId(int $productSnapshotId): static
    {
        $this->productSnapshotId = $productSnapshotId;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function setSort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getUnitDiscount(): ?string
    {
        return $this->unitDiscount;
    }

    public function setUnitDiscount(string $unitDiscount): static
    {
        $this->unitDiscount = $unitDiscount;

        return $this;
    }

    public function getFinalUnitPrice(): ?string
    {
        return $this->finalUnitPrice;
    }

    public function setFinalUnitPrice(string $finalUnitPrice): static
    {
        $this->finalUnitPrice = $finalUnitPrice;

        return $this;
    }

    public function getLineTotal(): ?string
    {
        return $this->lineTotal;
    }

    public function setLineTotal(string $lineTotal): static
    {
        $this->lineTotal = $lineTotal;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
