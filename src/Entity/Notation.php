<?php

namespace App\Entity;

use App\Repository\NotationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: NotationRepository::class)]
#[ORM\UniqueConstraint(columns: ["FEN", "text"])]
class Notation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $FEN = null;

    #[ORM\Column(length: 255)]
    #[Serializer\Groups(groups: ['Default'])]
    private ?string $text = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFEN(): ?string
    {
        return $this->FEN;
    }

    public function setFEN(string $FEN): static
    {
        $this->FEN = $FEN;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }
}
