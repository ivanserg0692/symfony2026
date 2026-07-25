<?php

namespace App\Entity;

use App\Order\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["order:list", "order:item"])]
    private ?int $id = null;

    #[ORM\Column(name: 'owner_id')]
    private ?int $ownerId = null;

    #[ORM\Column(length: 32, enumType: OrderStatus::class)]
    #[Groups(["order:list", "order:item"])]
    private OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column(name: 'operation_id', length: 64, nullable: true)]
    private ?string $operationId = null;

    #[ORM\Column(name: 'total_price', type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(["order:list", "order:item"])]
    private ?string $totalPrice = null;

    #[ORM\Column(name: 'total_discount', type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(["order:list", "order:item"])]
    private ?string $totalDiscount = null;

    #[ORM\Column(name: 'final_price', type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(["order:list", "order:item"])]
    private ?string $finalPrice = null;

    #[ORM\Column]
    #[Groups(["order:list", "order:item"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(["order:list", "order:item"])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    #[Groups(["order:item"])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId): static
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function setOperationId(?string $operationId): static
    {
        $this->operationId = $operationId;

        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getTotalDiscount(): ?string
    {
        return $this->totalDiscount;
    }

    public function setTotalDiscount(string $totalDiscount): static
    {
        $this->totalDiscount = $totalDiscount;

        return $this;
    }

    public function getFinalPrice(): ?string
    {
        return $this->finalPrice;
    }

    public function setFinalPrice(string $finalPrice): static
    {
        $this->finalPrice = $finalPrice;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
            }
        }

        return $this;
    }
}
