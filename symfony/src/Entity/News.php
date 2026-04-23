<?php

namespace App\Entity;

use App\Repository\NewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: NewsRepository::class)]
class News
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['news:read'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[Groups(['news:read'])]
    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[Groups(['news:read'])]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[Groups(['news:read'])]
    #[ORM\ManyToOne(inversedBy: 'news')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Blameable(on: 'create')] // Эту строку добавьте вручную
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $brief = null;

    #[ORM\ManyToOne(inversedBy: 'news')]
    #[ORM\JoinColumn(nullable: false)]
    private ?NewsStatus $status = null;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
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

    public function getBrief(): ?string
    {
        return $this->brief;
    }

    public function setBrief(string $brief): static
    {
        $this->brief = $brief;

        return $this;
    }

    public function getStatus(): ?NewsStatus
    {
        return $this->status;
    }

    public function setStatus(?NewsStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
