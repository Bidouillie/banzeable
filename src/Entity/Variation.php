<?php

namespace App\Entity;

use App\Repository\VariationRepository;
use Chess\FenToBoardFactory;
use Chess\Variant\Classical\Board;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: VariationRepository::class)]
class Variation extends PGNBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    #[Serializer\Groups(groups: ['Default'])]
    private ?bool $blackOrientation = null;

    #[ORM\ManyToOne(inversedBy: 'variations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Serializer\Groups(groups: ['course'])]
    private ?Course $course = null;

    #[ORM\ManyToOne]
    private ?Chapter $chapter = null;

    /**
     * @var Collection<int, VariationMove>
     */
    #[ORM\OneToMany(targetEntity: VariationMove::class, mappedBy: 'variation', cascade: ['persist'], orphanRemoval: true)]
    #[Serializer\Groups(['move'])]
    private Collection $moves;

    private ?string $PGN = null;

    public function __construct()
    {
        $this->moves = new ArrayCollection();
    }

    public function getFEN(): ?string
    {
        $moves = $this->getMoves();
        return count($moves) > 0 ? $moves->first()->getNotation()->getFEN() : parent::getFEN();
    }

    public function getPGN(): ?string
    {
        return $this->PGN;
    }

    public function setPGN(string $PGN): static
    {
        $this->PGN = $PGN;

        $lines = max(explode("\n", $PGN), explode(PHP_EOL, $PGN));

        $tags = [];
        $tagsKeyValue = [];
        $movetext = null;
        $resulttext = null;

        foreach ($lines as $line) {
            $line = trim($line);
            $matchesTag = preg_match('/^\[([a-zA-Z]+) "(.+)"\]$/', $line, $matches);
            if ($matchesTag) {
                $tags[] = $line;
                $tagsKeyValue[$matches[1]] = $matches[2];
            } else {
                $moveRegex = '[RNBQK]?[a-h]?[1-8]?x?[a-h][1-8](=[RNBQ])?(\+|#)?|O-O(-O)?';
                $matchesMovetext = preg_match("/^(([1-9][0-9]*\. ($moveRegex)( ($moveRegex))? ?)+)((0|1\/2|1)-(0|1\/2|1)|\*)?$/", $line, $matches);

                if ($matchesMovetext) {
                    $movetext = $matches[1];
                    $resulttext = $matches[1];
                }
            }
        }

        if (!isset($movetext)) {
            throw new \Exception("Missing Movetext");
        }

        // $this->PGN = implode(PHP_EOL, array_merge($tags, [$movetext . $resulttext]));

        $this->setTags($tagsKeyValue);

        $moves = trim(preg_replace('/[1-9][0-9]*\. /', '', $movetext));

        $this->setMoves($moves);

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isBlackOrientation(): ?bool
    {
        return $this->blackOrientation;
    }

    public function setBlackOrientation(bool $blackOrientation): static
    {
        $this->blackOrientation = $blackOrientation;

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

    public function getChapter(): ?Chapter
    {
        return $this->chapter;
    }

    public function setChapter(?Chapter $chapter): static
    {
        $this->chapter = $chapter;

        return $this;
    }

    /**
     * @return Collection<int, VariationMove>
     */
    public function getMoves(): Collection
    {
        return $this->moves;
    }

    public function setMoves(string $moves): static
    {
        $board = $this->getFEN() ? FenToBoardFactory::create($this->getFEN()) : new Board();

        foreach (explode(' ', $moves) as $moveText) {
            $move = new VariationMove();
            $notation = new Notation();

            $notation->setFEN($board->toFen());
            $notation->setText($moveText);

            $move->setNotation($notation);

            $this->addMove($move);

            $board->play($board->turn, $move->getNotation()->getText());
        }

        return $this;
    }

    public function addMove(VariationMove $move): static
    {
        if (!$this->moves->contains($move)) {
            $this->moves->add($move);
            $move->setVariation($this);
        }

        return $this;
    }

    public function removeMove(VariationMove $move): static
    {
        if ($this->moves->removeElement($move)) {
            // set the owning side to null (unless already changed)
            if ($move->getVariation() === $this) {
                $move->setVariation(null);
            }
        }

        return $this;
    }
}
