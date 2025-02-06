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

    /**
     * @return MovePopularityMaster[]
     */
    public function findBy(array $criteria, array|null $orderBy = null, int|null $limit = null, int|null $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    // TODO search by whole id (variant, speeds...)
    /**
     * @return array<string,MovePopularityMaster>
     */
    public function findGroupedByLan(string $fen, &$nbGames = null)
    {
        $qb = $this->createQueryBuilder('mp')
            ->where('mp.fen = :fen')
            ->setParameter('fen', $fen);

        $moves = $qb->getQuery()->getResult();

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

    /**
     * @return string[]
     */
    public function findByFenGrouped(string|array $fen, array $criteria = [])
    {
        $qb = $this->createQueryBuilder('move')
            ->select('move.fen')
            ->where('move.fen');

        if (is_array($fen)) {
            $qb->where('move.fen IN (:fen)');
        } else {
            $qb->where('move.fen = :fen');
        }
        foreach (array_keys($criteria) as $key) {
            $qb->andWhere("move.$key = :$key");
        }
        $qb->addGroupBy('move.fen');

        $qb->setParameter('fen', $fen);
        foreach ($criteria as $key => $value) {
            $qb->setParameter($key, $value);
        }

        $moves = $qb->getQuery()->getSingleColumnResult();

        return $moves;
    }
}
