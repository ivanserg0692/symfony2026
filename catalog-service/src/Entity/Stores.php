<?php

namespace App\Entity;

use App\Repository\StoresRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoresRepository::class)]
class Stores
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, StoresElementsStocks>
     */
    #[ORM\OneToMany(targetEntity: StoresElementsStocks::class, mappedBy: 'store', orphanRemoval: true)]
    private Collection $elementStocks;

    public function __construct()
    {
        $this->elementStocks = new ArrayCollection();
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

    /**
     * @return Collection<int, StoresElementsStocks>
     */
    public function getElementStocks(): Collection
    {
        return $this->elementStocks;
    }

    public function addElementStock(StoresElementsStocks $elementStock): static
    {
        if (!$this->elementStocks->contains($elementStock)) {
            $this->elementStocks->add($elementStock);
            $elementStock->setStore($this);
        }

        return $this;
    }

    public function removeElementStock(StoresElementsStocks $elementStock): static
    {
        if ($this->elementStocks->removeElement($elementStock)) {
            // set the owning side to null (unless already changed)
            if ($elementStock->getStore() === $this) {
                $elementStock->setStore(null);
            }
        }

        return $this;
    }
}
