<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Move;
use App\Entity\RepertoirePosition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Move>
 */
class MoveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Move::class);
    }

    /**
     * @return Move|null
     */
    public function findOneBy(array $criteria, array|null $orderBy = null): object|null
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    /**
     * @return array<string,Move>
     */
    public function findGroupedByLan(Course $course, string $fen): array
    {
        $qb = $this->createQueryBuilder('move')
            ->andWhere('move.course = :course')
            ->andWhere('move.fenFrom = :fen')
            ->setParameter('course', $course)
            ->setParameter('fen', $fen);

        /**
         * @var Move[] $moves
         */
        $moves = $qb->getQuery()->getResult();

        $movesGrouped = array_reduce($moves, function ($carry, $move) {
            $carry[$move->getLan()] = $move;
            return $carry;
        }, []);

        return $movesGrouped;
    }

    /**
     * @return array<string,array<string,Move>>
     */
    public function findGroupedByFenLan(Course $course, array $fens): array
    {
        $qb = $this->createQueryBuilder('move')
            ->andWhere('move.course = :course')
            ->andWhere('move.fenFrom IN (:fens)')
            ->setParameter('course', $course)
            ->setParameter('fens', $fens);

        $moves = $qb->getQuery()->getResult();

        $movesGrouped = array_reduce($moves, function ($carry, Move $move) {
            if (!isset($carry[$move->getFenFrom()])) {
                $carry[$move->getFenFrom()] = [];
            }
            $carry[$move->getFenFrom()][$move->getLan()] = $move;
            return $carry;
        }, []);

        return $movesGrouped;
    }

    /**
     * @return array<string,array<string,Move>>
     */
    public function findFromPositionGply(RepertoirePosition $position): array
    {
        $qb = $this->createQueryBuilder('move')
            ->leftJoin('move.positionFrom', 'positionFrom')
            ->leftJoin('move.positionTo', 'positionTo')
            ->andWhere('move.course = :course')
            ->andWhere('positionFrom.gply >= :gply OR positionTo.fen = :fen')
            ->setParameter('course', $position->getCourse())
            ->setParameter('gply', $position->getGply())
            ->setParameter('fen', $position->getFen());

        $moves = $qb->getQuery()->getResult();

        return $moves;
    }

    /**
     * @return array<string,array<string,true>>
     */
    public function findSavedGroupedByFenLan(Course $course, array $fens): array
    {
        $qb = $this->createQueryBuilder('move')
            ->select('move.fenFrom, move.lan')
            ->andWhere('move.course = :course')
            ->andWhere('move.fenFrom IN (:fens)')
            ->setParameter('course', $course)
            ->setParameter('fens', $fens);

        $moves = $qb->getQuery()->getArrayResult();

        $movesSavedGrouped = array_reduce($moves, function ($carry, $move) {
            if (!isset($carry[$move['fenFrom']])) {
                $carry[$move['fenFrom']] = [];
            }
            $carry[$move['fenFrom']][$move['lan']] = true;
            return $carry;
        }, []);

        return $movesSavedGrouped;
    }
}
