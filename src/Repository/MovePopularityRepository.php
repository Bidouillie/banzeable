<?php

namespace App\Repository;

use App\DTO\MovePopularityWithTotalDTO;
use App\DTO\MovePopularityWithTotalFenDTO;
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
     * @return null|false|array{nbGames:int,moves:array<string,MovePopularityWithTotalDTO>}
     */
    public function findWithTotalGroupedByLan(string $fen)
    {
        $qb = $this->createQueryBuilder('mp')
            ->select(sprintf('NEW %s(mp.lan, mp.white, mp.black, mp.draws, mp.white + mp.black + mp.draws)', MovePopularityWithTotalDTO::class))
            ->andWhere('mp.fen = :fen')
            ->setParameter('fen', $fen);

        /**
         * @var MovePopularityWithTotalDTO[] $moves
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
            if ($move->lan === '-') {
                if (!isset($move->total)) {
                    return false;
                }
                $movesGrouped['nbGames'] = $move->total;
            } else {
                $movesGrouped['moves'][$move->lan] = $move;
            }
        }

        return $movesGrouped;
    }

    // TODO search by whole id (variant, speeds...)
    /**
     * @return array<false|array{nbGames:int,moves:array<string,MovePopularityWithTotalDTO>}>
     */
    public function findGroupedByFenLan(array $fens)
    {
        $qb = $this->createQueryBuilder('mp')
            ->select(sprintf('NEW %s(mp.fen, mp.lan, mp.white, mp.black, mp.draws, mp.white + mp.black + mp.draws)', MovePopularityWithTotalFenDTO::class))
            ->andWhere('mp.fen in (:fens)')
            ->setParameter('fens', $fens);

        /**
         * @var MovePopularityWithTotalFenDTO[] $moves
         */
        $moves = $qb->getQuery()->getResult();

        $movesGrouped = [];
        foreach ($moves as $move) {
            if (!isset($movesGrouped[$move->fen])) {
                $movesGrouped[$move->fen] = [
                    'moves' => [],
                ];
            }
            if ($move->lan === '-') {
                if (isset($move->total)) {
                    $movesGrouped[$move->fen]['nbGames'] = $move->total;
                } else {
                    $movesGrouped[$move->fen] = false;
                }
            } else {
                $movesGrouped[$move->fen]['moves'][$move->lan] = $move;
            }
        }

        return $movesGrouped;
    }

    /**
     * @return array<string,bool>
     */
    public function findSavedByFenGrouped(array $fens)
    {
        $qb = $this->createQueryBuilder('mp')
            ->select('mp.fen, mp.white + mp.black + mp.draws AS total')
            ->andWhere('mp.lan = :lan')
            ->andWhere('mp.fen IN (:fens)')
            ->setParameter('lan', '-')
            ->setParameter('fens', $fens);

        $moves = $qb->getQuery()->getArrayResult();

        return array_reduce($moves, function ($carry, $move) {
            $carry[$move['fen']] = isset($move['total']);
            return $carry;
        }, []);
    }
}
