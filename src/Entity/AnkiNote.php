<?php

namespace App\Entity;

use App\Repository\AnkiNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnkiNoteRepository::class)]
#[ORM\Table(name: "anki_note")]
#[ORM\Index(name: "anki_key_index", columns: ["anki_key"])]
class AnkiNote
{
    #[ORM\Id]
    #[ORM\Column]
    private ?string $guid = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $ankiKey = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $extra = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $json = null;

    private ?string $noteModelUuid = null;

    private ?array $tags = null;

    public function getGuid(): ?string
    {
        return $this->guid;
    }

    public function setGuid(string $guid): static
    {
        $this->guid = $guid;

        return $this;
    }

    public function getAnkiKey(): ?string
    {
        return $this->ankiKey;
    }

    public function setAnkiKey(string $ankiKey): static
    {
        $this->ankiKey = $ankiKey;

        return $this;
    }

    public function getExtra(): ?bool
    {
        return $this->extra;
    }

    public function setExtra(bool $extra): static
    {
        $this->extra = $extra;

        return $this;
    }

    public function getJson(): ?string
    {
        return $this->json;
    }

    public function setJson(?string $json): static
    {
        $this->json = $json;

        return $this;
    }

    public function getModelUuid(): ?string
    {
        return $this->noteModelUuid;
    }

    public function setModelUuid(string $noteModelUuid): static
    {
        $this->noteModelUuid = $noteModelUuid;

        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }
}
