<?php

namespace App\Repository;

use App\Entity\MovePopularityMaster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovePopularity>
 */
class MovePopularityMasterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovePopularityMaster::class);
    }

    // TODO search by whole id (variant, speeds...)
    /**
     * @return array<string,MovePopularityMaster>
     */
    public function findByFenLan(string $fen, &$nbGames = null)
    {
        $qb = $this->createQueryBuilder('mp')
            ->where('mp.fen = :fen')
            ->setParameter('fen', $fen);

        $moves = $qb->getQuery()->getResult();

        $moves = array_reduce($moves, function ($carry, $move) use (&$nbGames) {
            if ($move->getSan() === '-') {
                $nbGames = $move->getTotal() ?? 0;
            } else {
                $carry[$move->getLan()] = $move;
            }
            return $carry;
        }, []);

        return $moves;
    }

    /**
     * @return string[]
     */
    public function findByFENGrouped(string|array $FEN, array $criteria = [])
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
