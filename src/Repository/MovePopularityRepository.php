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

    /**
     * @return string[]
     */
    public function findGroupedByFEN(string|array $FEN, array $criteria = [])
    {
        $qb = $this->createQueryBuilder('move')
            ->select('move.FEN')
            ->where('move.FEN');

        if (is_array($FEN)) {
            $qb->where('move.FEN IN (:FEN)');
        } else {
            $qb->where('move.FEN = :FEN');
        }
        foreach (array_keys($criteria) as $key) {
            $qb->andWhere("move.$key = :$key");
        }
        $qb->addGroupBy('move.FEN');

        $qb->setParameter('FEN', $FEN);
        foreach ($criteria as $key => $value) {
            $qb->setParameter($key, $value);
        }

        $moves = $qb->getQuery()->getSingleColumnResult();

        return $moves;
    }
}
