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
     * @return Move[]
     */
    public function findByFENReachedFromCourse(string|array $FEN, Course $course, string $byKey = null)
    {
        $qb = $this->createQueryBuilder('move')
            ->innerJoin('move.variation', 'variation')
            ->where('variation.course = :course');

        if (is_array($FEN)) {
            $qb->andWhere('move.FENReached IN (:FEN)');
        } else {
            $qb->andWhere('move.FENReached = :FEN');
        }

        $qb->setParameter('course', $course)
            ->setParameter('FEN', $FEN);

        $moves = $qb->getQuery()->getResult();

        switch ($byKey) {
            case 'FEN':
                return array_reduce($moves, function ($carry, $move) {
                    $carry[$move->getFENReached()] = $move;
                    return $carry;
                }, []);
            case 'SAN':
                return array_reduce($moves, function ($carry, $move) {
                    $carry[$move->getFENReached()] = $move;
                    return $carry;
                }, []);
        }

        return $moves;
    }
}
