<?php

namespace App\Entity;

use App\Repository\PGNRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PGNRepository::class)]
class PGN
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $event = null;

    #[ORM\Column(length: 255)]
    private ?string $site = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    private ?string $round = null;

    #[ORM\Column(length: 255)]
    private ?string $white = null;

    #[ORM\Column(length: 255)]
    private ?string $black = null;

    #[ORM\Column(length: 255)]
    private ?string $result = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $FEN = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(string $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getRound(): ?string
    {
        return $this->round;
    }

    public function setRound(string $round): static
    {
        $this->round = $round;

        return $this;
    }

    public function getWhite(): ?string
    {
        return $this->white;
    }

    public function setWhite(string $white): static
    {
        $this->white = $white;

        return $this;
    }

    public function getBlack(): ?string
    {
        return $this->black;
    }

    public function setBlack(string $black): static
    {
        $this->black = $black;

        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;

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
}
