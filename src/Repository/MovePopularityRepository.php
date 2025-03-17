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
     * 
     * @return null|false|array{nbGames:int,moves:array<string,MovePopularity>}
     */
    public function findGroupedByLan(string $fen)
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

        $movesGrouped = [
            'nbGames' => 0,
            'moves' => [],
        ];
        foreach ($moves as $move) {
            if ($move->getLan() === '-') {
                $total = $move->getTotal();
                if (!isset($total)) {
                    return false;
                }
                $movesGrouped['nbGames'] = $total;
            } else {
                $movesGrouped['moves'][$move->getLan()] = $move;
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
     * @return array<string,bool>
     */
    public function findSavedByFenGrouped(string|array $fen, array $criteria = [])
    {
        $qb = $this->createQueryBuilder('mp')
            ->select('mp.fen, mp.white')
            ->andWhere('mp.lan = :lan');

        if (is_array($fen)) {
            $qb->andWhere('mp.fen IN (:fen)');
        } else {
            $qb->andWhere('mp.fen = :fen');
        }
        foreach (array_keys($criteria) as $key) {
            $qb->andWhere("mp.$key = :$key");
        }

        $qb->setParameter('lan', '-');
        $qb->setParameter('fen', $fen);
        foreach ($criteria as $key => $value) {
            $qb->setParameter($key, $value);
        }

        $moves = $qb->getQuery()->getArrayResult();

        return array_reduce($moves, function ($carry, $move) {
            $carry[$move['fen']] = isset($move['white']);
            return $carry;
        }, []);
    }
}
