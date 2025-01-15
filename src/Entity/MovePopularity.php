<?php

namespace App\Entity;

use App\Repository\MovePopularityRepository;
use Chess\FenToBoardFactory;
use Chess\Variant\AbstractBoard;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovePopularityRepository::class)]
class MovePopularity
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $speeds = null;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $ratings = null;

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

    #[ORM\Column(nullable: true)]
    private ?int $white = null;

    #[ORM\Column(nullable: true)]
    private ?int $black = null;

    #[ORM\Column(nullable: true)]
    private ?int $draws = null;

    private ?AbstractBoard $board = null;

    public function __construct()
    {
        $this->setSpeeds('rapid');
        $this->setRatings('1600,1800');
        $this->setSince('2021-01');
        $this->setUntil('2024-12');
    }

    public function getSpeeds(): ?string
    {
        return $this->speeds;
    }

    public function setSpeeds(string $speeds): static
    {
        $this->speeds = $speeds;

        return $this;
    }

    public function getRatings(): ?string
    {
        return $this->ratings;
    }

    public function setRatings(string $ratings): static
    {
        $this->ratings = $ratings;

        return $this;
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

    public function getNextFEN(): ?string
    {
        if (isset($this->board)) {
            return $this->board->toFen();
        }
        if (isset($this->FEN) && isset($this->san)) {
            $this->board = FenToBoardFactory::create($this->FEN);
            $this->board->play($this->board->turn, $this->san);
            return $this->board->toFen();
        }
        return null;
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

    public function getLAN(): ?string
    {
        if (isset($this->board)) {
            $last = end($this->board->history);
            return $last['from'] . $last['to'];
        }
        if (isset($this->FEN) && isset($this->san)) {
            $this->board = FenToBoardFactory::create($this->FEN);
            $this->board->play($this->board->turn, $this->san);
            $last = end($this->board->history);
            return $last['from'] . $last['to'];
        }
        return null;
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
}
