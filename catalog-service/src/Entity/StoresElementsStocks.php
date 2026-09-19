<?php

namespace App\Entity;

use App\Repository\StoresElementsStocksRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoresElementsStocksRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_store_element_stock', columns: ['store_id', 'element_id'])]
class StoresElementsStocks
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'elementStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stores $store = null;

    #[ORM\ManyToOne(inversedBy: 'storeStocks')]
    #[ORM\JoinColumn(name: 'element_id', referencedColumnName: 'id', nullable: false)]
    private ?CatalogElements $element = null;

    #[ORM\Column]
    private ?int $stock = null;

    public function getStore(): ?Stores
    {
        return $this->store;
    }

    public function setStore(?Stores $store): static
    {
        if ($this->store === $store) {
            return $this;
        }

        $previousStore = $this->store;
        $this->store = $store;

        $previousStore?->removeElementStock($this);

        if ($store !== null && !$store->getElementStocks()->contains($this)) {
            $store->addElementStock($this);
        }

        return $this;
    }

    public function getElement(): ?CatalogElements
    {
        return $this->element;
    }

    public function setElement(?CatalogElements $element): static
    {
        if ($this->element === $element) {
            return $this;
        }

        $previousElement = $this->element;
        $this->element = $element;

        $previousElement?->removeStoreStock($this);

        if ($element !== null && !$element->getStoreStocks()->contains($this)) {
            $element->addStoreStock($this);
        }

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }
}
