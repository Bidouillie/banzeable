<?php

namespace App\Entity;

use App\Repository\RepertoirePositionRepository;
use Chess\FenToBoardFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepertoirePositionRepository::class)]
class RepertoirePosition
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $fen = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 9, options: ['default' => '1'])]
    private ?string $expectedPercentage = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 9, options: ['default' => '0'])]
    private ?string $completion = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    /**
     * @var Collection<int,Move>
     */
    #[ORM\OneToMany(targetEntity: Move::class, mappedBy: 'positionTo', orphanRemoval: true)]
    private Collection $previousMoves;

    /**
     * @var Collection<int,Move>
     */
    #[ORM\OneToMany(targetEntity: Move::class, mappedBy: 'positionFrom', orphanRemoval: true)]
    private Collection $nextMoves;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'fen', referencedColumnName: 'fen', nullable: false)]
    private ?Position $position = null;

    public function __construct()
    {
        $this->previousMoves = new ArrayCollection();
        $this->nextMoves = new ArrayCollection();
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

    public function getExpectedPercentage(): ?float
    {
        return isset($this->expectedPercentage) ? floatval($this->expectedPercentage) : null;
    }

    public function setExpectedPercentage(string $expectedPercentage): static
    {
        $this->expectedPercentage = $expectedPercentage;

        return $this;
    }

    public function getCompletion(): ?float
    {
        return isset($this->completion) ? floatval($this->completion) : null;
    }

    public function setCompletion(float $completion): static
    {
        $this->completion = number_format($completion, 9);

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

    /**
     * @return Collection<int,Move>
     */
    public function getPreviousMoves(): Collection
    {
        return $this->previousMoves;
    }

    public function addPreviousMove(Move $previousMove): static
    {
        if (!$this->previousMoves->contains($previousMove)) {
            $this->previousMoves->add($previousMove);
            $previousMove->setPositionTo($this);
        }

        return $this;
    }

    public function removePreviousMove(Move $previousMove): static
    {
        if ($this->previousMoves->removeElement($previousMove)) {
            // set the owning side to null (unless already changed)
            if ($previousMove->getPositionFrom() === $this) {
                $previousMove->setPositionFrom(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int,Move>
     */
    public function getNextMoves(): Collection
    {
        return $this->nextMoves;
    }

    public function addNextMove(Move $nextMove): static
    {
        if (!$this->nextMoves->contains($nextMove)) {
            $this->nextMoves->add($nextMove);
            $nextMove->setPositionFrom($this);
        }

        return $this;
    }

    public function removeNextMove(Move $nextMove): static
    {
        if ($this->nextMoves->removeElement($nextMove)) {
            // set the owning side to null (unless already changed)
            if ($nextMove->getPositionTo() === $this) {
                $nextMove->setPositionTo(null);
            }
        }

        return $this;
    }

    //TODO optimize
    public function isAncestorPosition(RepertoirePosition $position)
    {
        if ($this->getFen() === $position->getFen()) {
            return true;
        }

        if (count($this->getPreviousMoves()) < 1) {
            return false;
        }

        $found = false;
        foreach ($this->getPreviousMoves() as $move) {
            $found = $found || ($move->getPositionFrom() !== null && $move->getPositionFrom()->isAncestorPosition($position));
        }
        return $found;
    }

    public function isMyTurn(): bool
    {
        return ($this->getCourse()->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($this->getFen())->turn;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(?Position $position): static
    {
        $this->position = $position;
        $this->fen = $position->getFen();

        return $this;
    }
}
