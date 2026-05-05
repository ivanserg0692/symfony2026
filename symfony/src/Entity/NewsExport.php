<?php

namespace App\Entity;

use App\Repository\NewsExportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsExportRepository::class)]
class NewsExport
{
    #[ORM\Id]
    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MessengerBatch $messengerBatch;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $filePath = null;

    public function __construct(MessengerBatch $messengerBatch)
    {
        $this->messengerBatch = $messengerBatch;
    }

    public function getId(): ?int
    {
        return $this->messengerBatch->getId();
    }

    public function getMessengerBatch(): MessengerBatch
    {
        return $this->messengerBatch;
    }

    public function setMessengerBatch(MessengerBatch $messengerBatch): static
    {
        $this->messengerBatch = $messengerBatch;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }
}
