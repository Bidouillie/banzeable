<?php

namespace App\Entity;

use App\Repository\NotationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: NotationRepository::class)]
#[ORM\UniqueConstraint(columns: ["FEN", "text"])]
class Notation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $FEN = null;

    #[ORM\Column(length: 255)]
    #[Serializer\Groups(groups: ['Default'])]
    private ?string $text = null;

    /**
     * @var Collection<int, Move>
     */
    #[ORM\OneToMany(targetEntity: Move::class, mappedBy: 'notation')]
    #[Serializer\Groups(['move'])]
    private Collection $moves;

    public function __construct()
    {
        $this->moves = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFEN(): ?string
    {
        return $this->FEN;
    }

    public function setFEN(string $FEN): static
    {
        $this->FEN = $FEN;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return Collection<int, Move>
     */
    public function getMoves(): Collection
    {
        return $this->moves;
    }

    public function addMove(Move $move): static
    {
        if (!$this->moves->contains($move)) {
            $this->moves->add($move);
            $move->setNotation($this);
        }

        return $this;
    }

    public function removeMove(Move $move): static
    {
        if ($this->moves->removeElement($move)) {
            // set the owning side to null (unless already changed)
            if ($move->getNotation() === $this) {
                $move->setNotation(null);
            }
        }

        return $this;
    }
}
