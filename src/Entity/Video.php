<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Video
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $encryptedUrl = null;

    #[ORM\Column(type: 'text')]
    private ?string $encryptedTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedImage = null;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'videos')]
    private Collection $tags;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEncryptedUrl(): ?string { return $this->encryptedUrl; }
    public function setEncryptedUrl(string $url): self { $this->encryptedUrl = $url; return $this; }

    public function getEncryptedTitle(): ?string { return $this->encryptedTitle; }
    public function setEncryptedTitle(string $title): self { $this->encryptedTitle = $title; return $this; }

    public function getEncryptedDescription(): ?string { return $this->encryptedDescription; }
    public function setEncryptedDescription(?string $desc): self { $this->encryptedDescription = $desc; return $this; }

    public function getEncryptedImage(): ?string { return $this->encryptedImage; }
    public function setEncryptedImage(?string $img): self { $this->encryptedImage = $img; return $this; }

    public function getTags(): Collection { return $this->tags; }
    public function addTag(Tag $tag): self { if (!$this->tags->contains($tag)) { $this->tags->add($tag); } return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
}
