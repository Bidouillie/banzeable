<?php

namespace App\Entity;

use App\Repository\MovePopularityMasterRepository;
use Chess\FenToBoardFactory;
use Chess\Variant\AbstractBoard;
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
    private ?string $fen = null;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $lan = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date_created = null;

    #[ORM\Column(nullable: true)]
    private ?int $white = null;

    #[ORM\Column(nullable: true)]
    private ?int $black = null;

    #[ORM\Column(nullable: true)]
    private ?int $draws = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $opening = null;

    private ?AbstractBoard $board = null;

    public function __construct()
    {
        $this->setSince('2021');
        $this->setUntil('2024');
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

    public function getFen(): ?string
    {
        return $this->fen;
    }

    public function setFen(string $fen): static
    {
        $this->fen = $fen;

        return $this;
    }

    public function getNextFen(): ?string
    {
        if (isset($this->board)) {
            return $this->board->toFen();
        }
        if (isset($this->fen) && isset($this->lan)) {
            $this->board = FenToBoardFactory::create($this->fen);
            $this->board->play($this->board->turn, $this->lan);
            return $this->board->toFen();
        }
        return null;
    }

    public function getLan(): ?string
    {
        return $this->lan;
    }

    public function setLan(string $lan): static
    {
        $this->lan = $lan;

        return $this;
    }

    public function getSan(): ?string
    {
        if (!isset($this->board) && isset($this->fen) && isset($this->lan)) {
            $this->board = FenToBoardFactory::create($this->fen);
            $this->board->playLan($this->board->turn, $this->lan);
        }
        if (isset($this->board)) {
            $last = end($this->board->history);
            return $last['pgn'];
        }
    }

    /*
    public function getLan(): ?string
    {
        if (isset($this->board)) {
            $last = end($this->board->history);
            return $last['from'] . $last['to'];
        }
        if (isset($this->fen) && isset($this->san)) {
            $this->board = FenToBoardFactory::create($this->fen);
            $this->board->play($this->board->turn, $this->san);
            $last = end($this->board->history);
            return $last['from'] . $last['to'];
        }
        return null;
    }
    */

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
}
