<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?bool $active = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?string $description = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["catalog_element:list", "catalog_element:item"])]
    private ?string $pictureId = null;

    /**
     * @var Collection<int, CatalogSections>
     */
    #[ORM\ManyToMany(targetEntity: CatalogSections::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_catalog_sections')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'catalog_section_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(["catalog_element:item"])]
    private Collection $sections;

    #[ORM\OneToOne(mappedBy: 'product', targetEntity: CatalogElements::class)]
    private ?CatalogElements $catalogElement = null;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    /**
     * @return Collection<int, CatalogSections>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(CatalogSections $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->addProduct($this);
        }

        return $this;
    }

    public function removeSection(CatalogSections $section): static
    {
        if ($this->sections->removeElement($section)) {
            $section->removeProduct($this);
        }

        return $this;
    }

    public function getCatalogElement(): ?CatalogElements
    {
        return $this->catalogElement;
    }

    public function setCatalogElement(?CatalogElements $catalogElement): static
    {
        $this->catalogElement = $catalogElement;

        if ($catalogElement !== null && $catalogElement->getProduct() !== $this) {
            $catalogElement->setProduct($this);
        }

        return $this;
    }
}
