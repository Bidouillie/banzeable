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
    /**
     * @param string $fen
     * @param null|int $nbGames
     * 
     * @return null|false|array<string,MovePopularity>
     */
    public function findGroupedByLan(string $fen, &$nbGames = null)
    {
        $qb = $this->createQueryBuilder('mp')
            ->andWhere('mp.fen = :fen')
            ->setParameter('fen', $fen);

        /**
         * @var MovePopularity[] $moves
         */
        $moves = $qb->getQuery()->getResult();

        if (empty($moves)) {
            return null;
        }

        $movesGrouped = [];
        foreach ($moves as $move) {
            if ($move->getLan() === '-') {
                $total = $move->getTotal();
                if (!isset($total)) {
                    return false;
                }
                $nbGames = $total;
            } else {
                $movesGrouped[$move->getLan()] = $move;
            }
        }

        return $movesGrouped;
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
                $total = $move->getTotal();
                if (isset($total)) {
                    $movesGrouped[$move->getFen()]['nbGames'] = $total;
                } else {
                    $movesGrouped[$move->getFen()] = false;
                }
            } else {
                $movesGrouped[$move->getFen()]['moves'][$move->getLan()] = $move;
            }
        }

        return $movesGrouped;
    }

    /**
     * @return array<string,string>
     */
    public function findByFenGrouped(string|array $fen, array $criteria = [])
    {
        $qb = $this->createQueryBuilder('mp')
            ->select('mp.fen');

        if (is_array($fen)) {
            $qb->andWhere('mp.fen IN (:fen)');
        } else {
            $qb->andWhere('mp.fen = :fen');
        }
        foreach (array_keys($criteria) as $key) {
            $qb->andWhere("mp.$key = :$key");
        }
        $qb->addGroupBy('mp.fen');

        $qb->setParameter('fen', $fen);
        foreach ($criteria as $key => $value) {
            $qb->setParameter($key, $value);
        }

        /**
         * @var string[] $moves
         */
        $moves = $qb->getQuery()->getSingleColumnResult();

        return array_reduce($moves, function ($carry, $fen) {
            $carry[$fen] = $fen;
            return $carry;
        }, []);
    }
}
