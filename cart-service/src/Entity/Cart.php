<?php

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'carts')]
#[ORM\UniqueConstraint(name: 'uniq_carts_owner_id', columns: ['owner_id'])]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["cart:item"])]
    private ?int $id = null;

    #[ORM\Column(name: 'owner_id')]
    private ?int $ownerId = null;

    #[ORM\Column]
    #[Groups(["cart:item"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(["cart:item"])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, CartItem>
     */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist'], orphanRemoval: true)]
    #[Groups(["cart:item"])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(["cart:item"])]
    public function getStatus(): string
    {
        return "active";
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
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CartItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCart($this);
        }

        return $this;
    }

    public function removeItem(CartItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getCart() === $this) {
                $item->setCart(null);
            }
        }

        return $this;
    }
}
