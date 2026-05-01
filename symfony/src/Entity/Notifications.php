<?php

namespace App\Entity;

use App\Repository\NotificationsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: NotificationsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Notifications
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    #[Groups(['notification:read'])]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

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

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markAsRead(): static
    {
        $this->readAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function markAsUnread(): static
    {
        $this->readAt = null;

        return $this;
    }
}
