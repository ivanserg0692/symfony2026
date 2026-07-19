<?php

namespace App\Entity;

use App\Repository\CatalogSectionsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CatalogSectionsRepository::class)]
#[Gedmo\Tree(type: 'nested')]
class CatalogSections
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?string $slug = null;

    #[ORM\Column]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?bool $active = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?string $pictureId = null;

    #[ORM\Column]
    #[Gedmo\TreeLevel]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?int $level = null;

    #[ORM\Column]
    #[Gedmo\TreeLeft]
    private ?int $leftMargin = null;

    #[ORM\Column]
    #[Gedmo\TreeRight]
    private ?int $rightMargin = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $catalogSections;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'catalogSections')]
    #[Gedmo\TreeParent]
    private ?self $parent = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'sections')]
    private Collection $products;

    #[ORM\Column(options: ["default" => 100])]
    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    private ?int $sort = 100;

    public function __construct()
    {
        $this->catalogSections = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPictureId(): ?string
    {
        return $this->pictureId;
    }

    public function setPictureId(?string $pictureId): static
    {
        $this->pictureId = $pictureId;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function getLeftMargin(): ?int
    {
        return $this->leftMargin;
    }

    public function getRightMargin(): ?int
    {
        return $this->rightMargin;
    }

    /**
     * @return Collection<int, self>
     */
    public function getCatalogSections(): Collection
    {
        return $this->catalogSections;
    }

    public function addCatalogSection(self $catalogSection): static
    {
        if (!$this->catalogSections->contains($catalogSection)) {
            $this->catalogSections->add($catalogSection);
            $catalogSection->setParent($this);
        }

        return $this;
    }

    public function removeCatalogSection(self $catalogSection): static
    {
        if ($this->catalogSections->removeElement($catalogSection)) {
            if ($catalogSection->getParent() === $this) {
                $catalogSection->setParent(null);
            }
        }

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    #[Groups(["catalog_section:list", "catalog_section:item", "catalog_element:item"])]
    public function getParentId(): ?int
    {
        return $this->parent?->getId();
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getCatalogElements(): Collection
    {
        return $this->products;
    }

    public function addCatalogElement(CatalogElements $catalogElement): static
    {
        $catalogElement->addSection($this);

        return $this;
    }

    public function removeCatalogElement(CatalogElements $catalogElement): static
    {
        $catalogElement->removeSection($this);

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
}
