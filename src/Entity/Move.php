<?php

namespace App\Entity;

use App\Repository\MoveRepository;
use Chess\FenToBoardFactory;
use Chess\Variant\AbstractBoard;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoveRepository::class)]
class Move
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $fenFrom = null;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $fenTo = null;

    #[ORM\Column(length: 255)]
    private ?string $lan = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 9)]
    private ?string $selectedPercentage = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'repertoireMoves')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\ManyToOne(inversedBy: 'nextMoves')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'course_id', nullable: false)]
    #[ORM\JoinColumn(name: 'fen_from', referencedColumnName: 'fen', nullable: false)]
    private ?Position $positionFrom = null;

    #[ORM\ManyToOne(inversedBy: 'previousMoves')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'course_id', nullable: false)]
    #[ORM\JoinColumn(name: 'fen_to', referencedColumnName: 'fen', nullable: false)]
    private ?Position $positionTo = null;

    private ?MovePopularity $popularity = null;

    private ?MovePopularityMaster $popularityMaster = null;

    private ?AbstractBoard $board = null;

    public function getFenFrom(): ?string
    {
        return $this->fenFrom;
    }

    public function setFenFrom(string $fenFrom): static
    {
        $this->fenFrom = $fenFrom;

        return $this;
    }

    public function getFenTo(): ?string
    {
        return $this->fenTo;
    }

    public function setFenTo(string $fenTo): static
    {
        $this->fenTo = $fenTo;

        return $this;
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
        if (!isset($this->board) && isset($this->fenFrom) && isset($this->lan)) {
            $this->board = FenToBoardFactory::create($this->fenFrom);
            $this->board->playLan($this->board->turn, $this->lan);
        }
        if (isset($this->board)) {
            $last = end($this->board->history);
            return $last['pgn'];
        }
    }

    public function getSelectedPercentage(): ?float
    {
        return isset($this->selectedPercentage) ? floatval($this->selectedPercentage) : null;
    }

    public function setSelectedPercentage(string $selectedPercentage): static
    {
        $this->selectedPercentage = $selectedPercentage;

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

    public function getPositionFrom(): ?Position
    {
        return $this->positionFrom;
    }

    public function setPositionFrom(?Position $positionFrom): static
    {
        $this->positionFrom = $positionFrom;
        $this->fenFrom = $positionFrom->getFen();

        return $this;
    }

    public function getPositionTo(): ?Position
    {
        return $this->positionTo;
    }

    public function setPositionTo(?Position $positionTo): static
    {
        $this->positionTo = $positionTo;
        $this->fenTo = $positionTo->getFen();

        return $this;
    }

    public function getPopularity(): ?MovePopularity
    {
        return $this->popularity;
    }

    public function setPopularity(?MovePopularity $popularity): static
    {
        $this->popularity = $popularity;

        return $this;
    }

    public function getPopularityMaster(): ?MovePopularityMaster
    {
        return $this->popularityMaster;
    }

    public function setPopularityMaster(?MovePopularityMaster $popularityMaster): static
    {
        $this->popularityMaster = $popularityMaster;

        return $this;
    }

    public function isMyTurn(): bool
    {
        return ($this->getCourse()->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($this->getFenFrom())->turn;
    }
}
