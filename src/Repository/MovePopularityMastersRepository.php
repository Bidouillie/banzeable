<?php

namespace App\Repository;

use App\DTO\MovePopularityMastersWithTotalDTO;
use App\Entity\MovePopularityMasters;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovePopularity>
 */
class MovePopularityMastersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovePopularityMasters::class);
    }

    /**
     * @return MovePopularityMasters[]
     */
    public function findBy(array $criteria, array|null $orderBy = null, int|null $limit = null, int|null $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    // TODO search by whole id (variant, speeds...)
    /**
     * @param string $fen
     * 
     * @return null|false|array{nbGames:int,moves:array<string,MovePopularityMastersWithTotalDTO>}
     */
    public function findGroupedByLan(string $fen)
    {
        $qb = $this->createQueryBuilder('mp')
            ->select(sprintf('NEW %s(mp.lan, mp.white + mp.black + mp.draws)', MovePopularityMastersWithTotalDTO::class))
            ->andWhere('mp.fen = :fen')
            ->setParameter('fen', $fen);

        /**
         * @var MovePopularityMastersWithTotalDTO[] $moves
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
