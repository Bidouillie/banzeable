<?php

namespace App\Entity;

use App\Repository\PGNRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PGNRepository::class)]
class PGN extends PGNBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
}
