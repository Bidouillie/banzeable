<?php

namespace App\Entity;

use App\Repository\VariationMoveRepository;
use Chess\FenToBoardFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: VariationMoveRepository::class)]
class VariationMove
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'moves')]
    #[ORM\JoinColumn(nullable: false)]
    #[Serializer\Groups(['variation'])]
    private ?Variation $variation = null;

    #[ORM\ManyToOne(inversedBy: 'moves', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Serializer\Groups(groups: ['notation'])]
    private ?Notation $notation = null;

    /**
     * @var Collection<int, Notation>
     */
    #[ORM\ManyToMany(targetEntity: Notation::class)]
    private Collection $alternatives;

    #[ORM\Column(nullable: true)]
    private ?float $selectedMultiplier = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalSelectedMultiplier = null;

    #[ORM\Column(name: 'fen_reached', length: 255)]
    private ?string $FENReached = null;

    #[ORM\Column(nullable: true)]
    private ?float $coverage = null;

    public function __construct()
    {
        $this->alternatives = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariation(): ?Variation
    {
        return $this->variation;
    }

    public function setVariation(?Variation $variation): static
    {
        $this->variation = $variation;

        return $this;
    }

    public function getNotation(): ?Notation
    {
        return $this->notation;
    }

    public function setNotation(?Notation $notation): static
    {
        $this->notation = $notation;

        if (isset($notation)) {
            $board = FenToBoardFactory::create($notation->getFEN());
            $board->play($board->turn, $notation->getText());
            $this->FENReached = $board->toFen();
        } else {
            $this->FENReached = null;
        }

        return $this;
    }

    /**
     * @return Collection<int, Notation>
     */
    public function getAlternatives(): Collection
    {
        return $this->alternatives;
    }

    public function addAlternative(Notation $alternative): static
    {
        if (!$this->alternatives->contains($alternative)) {
            $this->alternatives->add($alternative);
        }

        return $this;
    }

    public function removeAlternative(Notation $alternative): static
    {
        $this->alternatives->removeElement($alternative);

        return $this;
    }

    public function getSelectedMultiplier(): ?float
    {
        return $this->selectedMultiplier;
    }

    public function setSelectedMultiplier(?float $selectedMultiplier): static
    {
        $this->selectedMultiplier = $selectedMultiplier;

        return $this;
    }

    public function getTotalSelectedMultiplier(): ?float
    {
        return $this->totalSelectedMultiplier;
    }

    public function setTotalSelectedMultiplier(?float $totalSelectedMultiplier): static
    {
        $this->totalSelectedMultiplier = $totalSelectedMultiplier;

        return $this;
    }

    public function getFENReached(): ?string
    {
        return $this->FENReached;
    }

    public function setFENReached(?string $FENReached): static
    {
        $this->FENReached = $FENReached;

        return $this;
    }

    public function getCoverage(): ?float
    {
        return $this->coverage;
    }

    public function setCoverage(?float $coverage): static
    {
        $this->coverage = $coverage;

        return $this;
    }
}
