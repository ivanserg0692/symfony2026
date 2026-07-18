<?php

namespace App\Entity;

use App\Repository\ProductSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductSnapshotRepository::class)]
class ProductSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["product_snapshot:list", "product_snapshot:item"])]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Groups(["product_snapshot:list", "product_snapshot:item"])]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: CatalogElements::class)]
    #[ORM\JoinColumn(name: 'original_product_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(["product_snapshot:item"])]
    private ?CatalogElements $originalProduct = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getOriginalProduct(): ?CatalogElements
    {
        return $this->originalProduct;
    }

    #[Groups(["product_snapshot:list", "product_snapshot:item"])]
    public function getOriginalProductId(): ?int
    {
        return $this->originalProduct?->getId();
    }

    public function setOriginalProduct(CatalogElements $originalProduct): static
    {
        $this->originalProduct = $originalProduct;

        return $this;
    }
}
