<?php

namespace App\Entity;

use App\Enum\NewsStatusCode;
use App\Repository\NewsStatusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsStatusRepository::class)]
class NewsStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true, enumType: NewsStatusCode::class)]
    private ?NewsStatusCode $code = null;

    /**
     * @var Collection<int, News>
     */
    #[ORM\OneToMany(targetEntity: News::class, mappedBy: 'status')]
    private Collection $news;

    public function __construct()
    {
        $this->news = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? sprintf('Status #%d', $this->id ?? 0);
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

    public function getCode(): ?NewsStatusCode
    {
        return $this->code;
    }

    public function setCode(NewsStatusCode $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return Collection<int, News>
     */
    public function getNews(): Collection
    {
        return $this->news;
    }

    public function addNews(News $news): static
    {
        if (!$this->news->contains($news)) {
            $this->news->add($news);
            $news->setStatus($this);
        }

        return $this;
    }

    public function removeNews(News $news): static
    {
        if ($this->news->removeElement($news)) {
            if ($news->getStatus() === $this) {
                $news->setStatus(null);
            }
        }

        return $this;
    }
}
