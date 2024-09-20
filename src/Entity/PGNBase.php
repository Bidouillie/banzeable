<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class PGNBase
{
    private static $tags = ['event', 'site', 'date', 'round', 'white', 'black', 'result', 'FEN'];
   
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $event = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $round = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $white = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $black = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $result = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $FEN = null;

    protected function setTags(array $tags): void {
        $tagsToLower = array_map('strtolower', self::$tags);
        foreach($tags as $key => $value) {
            $index = array_search(strtolower($key), $tagsToLower);
            if($index !== false) {
                $setter = 'set' . ucfirst(self::$tags[$index]);
                call_user_func([$this, $setter], $value);
            }
        }
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(string $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getRound(): ?string
    {
        return $this->round;
    }

    public function setRound(string $round): static
    {
        $this->round = $round;

        return $this;
    }

    public function getWhite(): ?string
    {
        return $this->white;
    }

    public function setWhite(string $white): static
    {
        $this->white = $white;

        return $this;
    }

    public function getBlack(): ?string
    {
        return $this->black;
    }

    public function setBlack(string $black): static
    {
        $this->black = $black;

        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;

        return $this;
    }

    public function getFEN(): ?string
    {
        return $this->FEN;
    }

    public function setFEN(?string $FEN): static
    {
        $this->FEN = $FEN;

        return $this;
    }
}
