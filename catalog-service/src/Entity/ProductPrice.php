<?php

namespace App\Entity;

use App\Repository\ProductPriceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductPriceRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_product_price_type', columns: ['product_id', 'price_type_id'])]
class ProductPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productPrices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CatalogElements $product = null;

    #[ORM\ManyToOne(inversedBy: 'productPrices')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?PriceType $priceType = null;

    #[ORM\Column]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?int $price = null;

    #[ORM\Column(length: 3)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?string $currency = null;

    #[ORM\Column]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?bool $active = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?CatalogElements
    {
        return $this->product;
    }

    public function setProduct(?CatalogElements $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getPriceType(): ?PriceType
    {
        return $this->priceType;
    }

    public function setPriceType(?PriceType $priceType): static
    {
        $this->priceType = $priceType;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): static
    {
        $this->validTo = $validTo;

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
