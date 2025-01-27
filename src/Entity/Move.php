<?php

namespace App\Entity;

use App\Repository\MoveRepository;
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
    private ?string $san = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'repertoireMoves')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\ManyToOne(inversedBy: 'previousMoves')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'course_id', nullable: false)]
    #[ORM\JoinColumn(name: 'fen_from', referencedColumnName: 'fen', nullable: false)]
    private ?Position $positionFrom = null;

    #[ORM\ManyToOne(inversedBy: 'nextMoves')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'course_id', nullable: false)]
    #[ORM\JoinColumn(name: 'fen_to', referencedColumnName: 'fen', nullable: false)]
    private ?Position $positionTo = null;

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

    public function getSan(): ?string
    {
        return $this->san;
    }

    public function setSan(string $san): static
    {
        $this->san = $san;

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

        return $this;
    }

    public function getPositionTo(): ?Position
    {
        return $this->positionTo;
    }

    public function setPositionTo(?Position $positionTo): static
    {
        $this->positionTo = $positionTo;

        return $this;
    }
}
