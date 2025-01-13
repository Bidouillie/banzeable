<?php

namespace App\Repository;

use App\Entity\MovePopularity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovePopularity>
 */
class MovePopularityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovePopularity::class);
    }

    // TODO search by whole id (variant, speeds...)
    /**
     * @return MovePopularity[]
     */
    public function findByFEN(string $FEN, &$games)
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.FEN = :FEN')
            ->setParameter('FEN', $FEN);

        $moves = $qb->getQuery()->getResult();

        foreach ($moves as $key => $move) {
            if ($move->getSan() === '-') {
                $games = $move->getTotal() ?? 0;
                unset($moves[$key]);
            }
        }

        return $moves;
    }
}
