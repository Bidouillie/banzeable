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
    public function findByFEN(string $FEN, &$nbGames)
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.FEN = :FEN')
            ->setParameter('FEN', $FEN);

        $moves = $qb->getQuery()->getResult();

        foreach ($moves as $key => $move) {
            if ($move->getSan() === '-') {
                $nbGames = $move->getTotal() ?? 0;
                unset($moves[$key]);
            }
        }

        return $moves;
    }

    // TODO search by whole id (variant, speeds...)
    /**
     * @return array<array{moves:array<MovePopularity>,nbGames:int}>
     */
    public function findGroupedByFENSAN(array $FENs)
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.FEN in (:FENs)')
            ->setParameter('FENs', $FENs);

        $moves = $qb->getQuery()->getResult();

        $movesGrouped = [];

        foreach ($moves as $move) {
            if (!isset($movesGrouped[$move->getFEN()])) {
                $movesGrouped[$move->getFEN()] = [
                    'moves' => [],
                ];
            }
            if ($move->getSan() === '-') {
                $movesGrouped[$move->getFEN()]['nbGames'] = $move->getTotal() ?? 0;
            } else {
                $movesGrouped[$move->getFEN()]['moves'][$move->getSAN()] = $move;
            }
        }

        return $movesGrouped;
    }

    /**
     * @return string[]
     */
    public function findByFENGrouped(string|array $FEN, array $criteria = [])
    {
        $qb = $this->createQueryBuilder('move')
            ->select('move.FEN');

        if (is_array($FEN)) {
            $qb->andWhere('move.FEN IN (:FEN)');
        } else {
            $qb->andWhere('move.FEN = :FEN');
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
