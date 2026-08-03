<?php

namespace App\Entity;

use App\Repository\CartItemRepository;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'cart_items')]
#[ORM\UniqueConstraint(name: 'uniq_cart_items_cart_catalog_element', columns: ['cart_id', 'catalog_element_id'])]
#[ORM\UniqueConstraint(name: 'uniq_cart_items_cart_sort', columns: ['cart_id', 'sort'])]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["cart:item"])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'cart_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Cart $cart = null;

    #[ORM\Column(name: 'catalog_element_id')]
    #[Groups(["cart:item"])]
    private ?int $catalogElementId = null;

    #[ORM\Column]
    #[Groups(["cart:item"])]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Groups(["cart:item"])]
    private ?int $sort = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(["cart:item"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(["cart:item"])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    public function getCatalogElementId(): ?int
    {
        return $this->catalogElementId;
    }

    public function setCatalogElementId(int $catalogElementId): static
    {
        $this->catalogElementId = $catalogElementId;

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
}
