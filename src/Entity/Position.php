<?php

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
class Position
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $fen = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    /**
     * @var Collection<int, Move>
     */
    #[ORM\OneToMany(targetEntity: Move::class, mappedBy: 'positionFrom', orphanRemoval: true)]
    private Collection $previousMoves;

    /**
     * @var Collection<int, Move>
     */
    #[ORM\OneToMany(targetEntity: Move::class, mappedBy: 'positionTo', orphanRemoval: true)]
    private Collection $nextMoves;

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
     * @return Collection<int, Move>
     */
    public function getPreviousMoves(): Collection
    {
        return $this->previousMoves;
    }

    public function addPreviousMove(Move $previousMove): static
    {
        if (!$this->previousMoves->contains($previousMove)) {
            $this->previousMoves->add($previousMove);
            $previousMove->setPositionFrom($this);
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
     * @return Collection<int, Move>
     */
    public function getNextMoves(): Collection
    {
        return $this->nextMoves;
    }

    public function addNextMove(Move $nextMove): static
    {
        if (!$this->nextMoves->contains($nextMove)) {
            $this->nextMoves->add($nextMove);
            $nextMove->setPositionTo($this);
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
}
