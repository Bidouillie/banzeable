<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Move;
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
}
