<?php

namespace App\Entity;

use App\Repository\CatalogElementsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CatalogElementsRepository::class)]
class CatalogElements
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pictureId = null;

    /**
     * @var Collection<int, CatalogSections>
     */
    #[ORM\ManyToMany(targetEntity: CatalogSections::class, inversedBy: 'catalogElements')]
    private Collection $sections;

    #[ORM\Column(options: ["default" => 100])]
    private ?int $sort = 100;

    /**
     * @var Collection<int, StoresElementsStocks>
     */
    #[ORM\OneToMany(targetEntity: StoresElementsStocks::class, mappedBy: 'element', orphanRemoval: true)]
    private Collection $storeStocks;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
        $this->storeStocks = new ArrayCollection();
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
        }

        return $this;
    }

    public function removeSection(CatalogSections $section): static
    {
        $this->sections->removeElement($section);

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

    /**
     * @return Collection<int, StoresElementsStocks>
     */
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
            // set the owning side to null (unless already changed)
            if ($storeStock->getElement() === $this) {
                $storeStock->setElement(null);
            }
        }

        return $this;
    }
}
