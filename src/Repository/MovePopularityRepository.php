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

    /**
     * @return MovePopularity[]
     */
    public function findBy(array $criteria, array|null $orderBy = null, int|null $limit = null, int|null $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    // TODO search by whole id (variant, speeds...)
    public function findGroupedByLan(string $fen, &$nbGames = null)
    {
        $qb = $this->createQueryBuilder('mp')
            ->andWhere('mp.fen = :fen')
            ->setParameter('fen', $fen);

        /**
         * @var MovePopularity[] $moves
         */
        $moves = $qb->getQuery()->getResult();

        /**
         * @var array<string,MovePopularity> $moves
         */
        $moves = array_reduce($moves, function ($carry, $move) use (&$nbGames) {
            if ($move->getLan() === '-') {
                $nbGames = $move->getTotal() ?? 0;
            } else {
                $carry[$move->getLan()] = $move;
            }
            return $carry;
        }, []);

        return $moves;
    }

    // TODO search by whole id (variant, speeds...)
    public function findGroupedByFenSan(array $fens)
    {
        $qb = $this->createQueryBuilder('mp')
            ->andWhere('mp.fen in (:fens)')
            ->setParameter('fens', $fens);

        /**
         * @var MovePopularity[] $moves
         */
        $moves = $qb->getQuery()->getResult();

        $movesGrouped = [];
        foreach ($moves as $move) {
            if (!isset($movesGrouped[$move->getFen()])) {
                $movesGrouped[$move->getFen()] = [
                    'moves' => [],
                ];
            }
            if ($move->getLan() === '-') {
                $movesGrouped[$move->getFen()]['nbGames'] = $move->getTotal() ?? 0;
            } else {
                $movesGrouped[$move->getFen()]['moves'][$move->getSan()] = $move;
            }
        }

        return $movesGrouped;
    }

    // TODO search by whole id (variant, speeds...)
    public function findGroupedByFenLan(array $fens)
    {
        $qb = $this->createQueryBuilder('mp')
            ->andWhere('mp.fen in (:fens)')
            ->setParameter('fens', $fens);

        /**
         * @var MovePopularity[] $moves
         */
        $moves = $qb->getQuery()->getResult();

        $movesGrouped = [];
        foreach ($moves as $move) {
            if (!isset($movesGrouped[$move->getFen()])) {
                $movesGrouped[$move->getFen()] = [
                    'moves' => [],
                ];
            }
            if ($move->getLan() === '-') {
                $movesGrouped[$move->getFen()]['nbGames'] = $move->getTotal() ?? 0;
            } else {
                $movesGrouped[$move->getFen()]['moves'][$move->getLan()] = $move;
            }
        }

        return $movesGrouped;
    }

    public function findByFENGrouped(string|array $FEN, array $criteria = [])
    {
        $qb = $this->createQueryBuilder('mp')
            ->select('mp.FEN');

        if (is_array($FEN)) {
            $qb->andWhere('mp.FEN IN (:FEN)');
        } else {
            $qb->andWhere('mp.FEN = :FEN');
        }
        foreach (array_keys($criteria) as $key) {
            $qb->andWhere("mp.$key = :$key");
        }
        $qb->addGroupBy('mp.FEN');

        $qb->setParameter('FEN', $FEN);
        foreach ($criteria as $key => $value) {
            $qb->setParameter($key, $value);
        }

        /**
         * @var string[] $moves
         */
        $moves = $qb->getQuery()->getSingleColumnResult();

        return $moves;
    }
}
