<?php

namespace App\Repository;

use App\Entity\Move;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Move>
 */
class MoveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Move::class);
    }

    /**
     * @return Move|null
     */
    public function findOneBy(array $criteria, array|null $orderBy = null): object|null
    {
        return parent::findOneBy($criteria, $orderBy);
    }
}
