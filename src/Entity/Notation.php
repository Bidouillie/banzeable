<?php

namespace App\Entity;

use App\Repository\NotationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotationRepository::class)]
class Notation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $FEN = null;

    #[ORM\Column(length: 255)]
    private ?string $notation = null;

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

    public function getNotation(): ?string
    {
        return $this->notation;
    }

    public function setNotation(string $notation): static
    {
        $this->notation = $notation;

        return $this;
    }
}
