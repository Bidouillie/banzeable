<?php

namespace App\Entity;

use App\Repository\VariationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VariationRepository::class)]
class Variation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $FEN = null;

    #[ORM\ManyToOne(inversedBy: 'variations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?PGN $PGN = null;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFEN(): ?string
    {
        return $this->FEN;
    }

    public function setFEN(?string $FEN): static
    {
        $this->FEN = $FEN;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getPGN(): ?PGN
    {
        return $this->PGN;
    }

    public function setPGN(?PGN $PGN): static
    {
        $this->PGN = $PGN;

        return $this;
    }
}
