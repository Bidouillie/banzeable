<?php

namespace App\Entity;

use App\Repository\MovePopularityMasterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovePopularityMasterRepository::class)]
class MovePopularityMaster
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $since = null;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $until = null;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $FEN = null;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $san = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date_created = null;

    #[ORM\Column]
    private ?int $white = null;

    #[ORM\Column]
    private ?int $black = null;

    #[ORM\Column]
    private ?int $draws = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $opening = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $nextMovesLoaded = null;

    public function __construct()
    {
        $this->setNextMovesLoaded(false);
    }

    public function getSince(): ?string
    {
        return $this->since;
    }

    public function setSince(string $since): static
    {
        $this->since = $since;

        return $this;
    }

    public function getUntil(): ?string
    {
        return $this->until;
    }

    public function setUntil(string $until): static
    {
        $this->until = $until;

        return $this;
    }

    public function getFEN(): ?string
    {
        return $this->FEN;
    }

    public function setFen(string $FEN): static
    {
        $this->FEN = $FEN;

        return $this;
    }

    public function getSan(): ?string
    {
        return $this->san;
    }

    public function setSan(string $san): static
    {
        $this->san = $san;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->date_created;
    }

    public function setDateCreated(\DateTimeInterface $date_created): static
    {
        $this->date_created = $date_created;

        return $this;
    }

    public function getWhite(): ?int
    {
        return $this->white;
    }

    public function setWhite(int $white): static
    {
        $this->white = $white;

        return $this;
    }

    public function getBlack(): ?int
    {
        return $this->black;
    }

    public function setBlack(int $black): static
    {
        $this->black = $black;

        return $this;
    }

    public function getDraws(): ?int
    {
        return $this->draws;
    }

    public function setDraws(int $draws): static
    {
        $this->draws = $draws;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->getWhite() + $this->getBlack() + $this->getDraws();
    }

    public function getOpening(): ?string
    {
        return $this->opening;
    }

    public function setOpening(?string $opening): static
    {
        $this->opening = $opening;

        return $this;
    }

    public function isNextMovesLoaded(): ?bool
    {
        return $this->nextMovesLoaded;
    }

    public function setNextMovesLoaded(bool $nextMovesLoaded): static
    {
        $this->nextMovesLoaded = $nextMovesLoaded;

        return $this;
    }
}
