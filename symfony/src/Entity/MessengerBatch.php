<?php

namespace App\Entity;

use App\MessengerBatch\MessengerBatchStatus;
use App\Repository\MessengerBatchRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: MessengerBatchRepository::class)]
class MessengerBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $status = MessengerBatchStatus::PENDING->value;

    #[ORM\Column]
    private int $totalJobs = 0;

    #[ORM\Column]
    private int $processedJobs = 0;

    #[ORM\Column]
    private int $failedJobs = 0;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(MessengerBatchStatus|string $status): static
    {
        $this->status = $status instanceof MessengerBatchStatus ? $status->value : $status;

        return $this;
    }

    public function getTotalJobs(): int
    {
        return $this->totalJobs;
    }

    public function setTotalJobs(int $totalJobs): static
    {
        $this->totalJobs = $totalJobs;

        return $this;
    }

    public function getProcessedJobs(): int
    {
        return $this->processedJobs;
    }

    public function setProcessedJobs(int $processedJobs): static
    {
        $this->processedJobs = $processedJobs;

        return $this;
    }

    public function getFailedJobs(): int
    {
        return $this->failedJobs;
    }

    public function setFailedJobs(int $failedJobs): static
    {
        $this->failedJobs = $failedJobs;

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

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }
}
