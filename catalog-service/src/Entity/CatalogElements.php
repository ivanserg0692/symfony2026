<?php

namespace App\Entity;

use App\Repository\CatalogElementsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CatalogElementsRepository::class)]
#[ORM\Index(
    name: "idx_catalog_elements_sort_id",
    fields: ["sort", "id"],
    options: ["order" => ["sort" => "DESC"]]
)]
class CatalogElements
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    // Extension entity for live-catalog relations; Product keeps the base product data.

    #[ORM\OneToOne(inversedBy: 'catalogElement', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(options: ["default" => 100])]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?int $sort = 100;

    /**
     * @var Collection<int, StoresElementsStocks>
     */
    #[ORM\OneToMany(targetEntity: StoresElementsStocks::class, mappedBy: 'element', orphanRemoval: true)]
    private Collection $storeStocks;

    /**
     * @var Collection<int, ProductPrice>
     */
    #[ORM\OneToMany(targetEntity: ProductPrice::class, mappedBy: 'product', orphanRemoval: true)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private Collection $productPrices;

    public function __construct()
    {
        $this->product = new Product();
        $this->product->setCatalogElement($this);
        $this->storeStocks = new ArrayCollection();
        $this->productPrices = new ArrayCollection();
    }

    #[Groups(["catalog_element:list", "catalog_element:item", "product_snapshot:list", "product_snapshot:item"])]
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

        if ($product->getCatalogElement() !== $this) {
            $product->setCatalogElement($this);
        }

        return $this;
    }

    #[Groups(["catalog_element:list", "catalog_element:item", "product_snapshot:list", "product_snapshot:item"])]
    public function getName(): ?string
    {
        return $this->product?->getName();
    }

    public function setName(string $name): static
    {
        $this->getProductModel()->setName($name);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->product?->getCreatedAt();
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->getProductModel()->setCreatedAt($createdAt);

        return $this;
    }

    #[Groups(["catalog_element:list", "catalog_element:item", "product_snapshot:list", "product_snapshot:item"])]
    public function isActive(): ?bool
    {
        return $this->product?->isActive();
    }

    public function setActive(bool $active): static
    {
        $this->getProductModel()->setActive($active);

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->product?->getCreatedBy();
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->getProductModel()->setCreatedBy($createdBy);

        return $this;
    }

    #[Groups(["catalog_element:list", "catalog_element:item", "product_snapshot:item"])]
    public function getDescription(): ?string
    {
        return $this->product?->getDescription();
    }

    public function setDescription(?string $description): static
    {
        $this->getProductModel()->setDescription($description);

        return $this;
    }

    #[Groups(["catalog_element:list", "catalog_element:item", "product_snapshot:list", "product_snapshot:item"])]
    public function getSlug(): ?string
    {
        return $this->product?->getSlug();
    }

    public function setSlug(string $slug): static
    {
        $this->getProductModel()->setSlug($slug);

        return $this;
    }

    #[Groups(["catalog_element:list", "catalog_element:item", "product_snapshot:list", "product_snapshot:item"])]
    public function getPictureId(): ?string
    {
        return $this->product?->getPictureId();
    }

    public function setPictureId(?string $pictureId): static
    {
        $this->getProductModel()->setPictureId($pictureId);

        return $this;
    }

    /**
     * @return Collection<int, CatalogSections>
     */
    #[Groups(["catalog_element:item"])]
    public function getSections(): Collection
    {
        return $this->getProductModel()->getSections();
    }

    public function addSection(CatalogSections $section): static
    {
        $this->getProductModel()->addSection($section);

        return $this;
    }

    public function removeSection(CatalogSections $section): static
    {
        $this->getProductModel()->removeSection($section);

        return $this;
    }

    #[Groups(["catalog_element:list", "catalog_element:item"])]
    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function setSort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    #[Groups(["catalog_element:list", "catalog_element:item"])]
    public function getTotalStock(): int
    {
        $totalStock = 0;

        foreach ($this->storeStocks as $storeStock) {
            $totalStock += $storeStock->getStock() ?? 0;
        }

        return $totalStock;
    }

    /**
     * @return Collection<int, Stores>
     */
    public function getStores(): Collection
    {
        $stores = new ArrayCollection();

        foreach ($this->storeStocks as $storeStock) {
            $store = $storeStock->getStore();

            if ($store !== null && !$stores->contains($store)) {
                $stores->add($store);
            }
        }

        return $stores;
    }

    /**
     * @return Collection<int, StoresElementsStocks>
     */
    public function getStoreStocks(): Collection
    {
        return $this->storeStocks;
    }

    public function addStoreStock(StoresElementsStocks $storeStock): static
    {
        if (!$this->storeStocks->contains($storeStock)) {
            $this->storeStocks->add($storeStock);
            $storeStock->setElement($this);
        }

        return $this;
    }

    public function removeStoreStock(StoresElementsStocks $storeStock): static
    {
        if ($this->storeStocks->removeElement($storeStock)) {
            if ($storeStock->getElement() === $this) {
                $storeStock->setElement(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductPrice>
     */
    public function getProductPrices(): Collection
    {
        return $this->productPrices;
    }

    public function addProductPrice(ProductPrice $productPrice): static
    {
        if (!$this->productPrices->contains($productPrice)) {
            $this->productPrices->add($productPrice);
            $productPrice->setProduct($this);
        }

        return $this;
    }

    public function removeProductPrice(ProductPrice $productPrice): static
    {
        if ($this->productPrices->removeElement($productPrice)) {
            if ($productPrice->getProduct() === $this) {
                $productPrice->setProduct(null);
            }
        }

        return $this;
    }

    private function getProductModel(): Product
    {
        if ($this->product === null) {
            $this->product = new Product();
            $this->product->setCatalogElement($this);
        }

        return $this->product;
    }
}
