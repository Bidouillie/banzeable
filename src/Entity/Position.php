<?php

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
class Position
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $fen = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $evaluation = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['default' => 0])]
    private ?int $mate = null;

    public function getFen(): ?string
    {
        return $this->fen;
    }

    public function setFen(string $fen): static
    {
        $this->fen = $fen;

        return $this;
    }

    public function getEvaluation(): ?string
    {
        return $this->evaluation;
    }

    public function setEvaluation(?string $evaluation): static
    {
        $this->evaluation = $evaluation;

        return $this;
    }

    public function getMate(): ?int
    {
        return $this->mate;
    }

    public function setMate(?int $mate): static
    {
        $this->mate = $mate;

        return $this;
    }
}
