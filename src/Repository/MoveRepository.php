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
    public function findByFENFromCourse(string|array $FEN, Course $course, string $byKey = null)
    {
        $qb = $this->createQueryBuilder('move')
            ->innerJoin('move.notation', 'notation')
            ->innerJoin('move.variation', 'variation')
            ->where('variation.course = :course');

        if (is_array($FEN)) {
            $qb->andWhere('notation.FEN IN (:FEN)');
        } else {
            $qb->andWhere('notation.FEN = :FEN');
        }

        $qb->setParameter('course', $course)
            ->setParameter('FEN', $FEN);

        $moves = $qb->getQuery()->getResult();

        switch ($byKey) {
            case 'FEN':
                return array_reduce($moves, function ($carry, $move) {
                    $carry[$move->getNotation()->getFEN()] = $move;
                    return $carry;
                }, []);
            case 'SAN':
                return array_reduce($moves, function ($carry, $move) {
                    $carry[$move->getNotation()->getText()] = $move;
                    return $carry;
                }, []);
        }

        return $moves;
    }

    /**
     * @return Move[][]
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

        $qb->orderBy('variation.id', 'ASC');

        $qb->setParameter('course', $course)
            ->setParameter('FEN', $FEN);

        $moves = $qb->getQuery()->getResult();

        switch ($byKey) {
            case 'FEN':
                return array_reduce($moves, function ($carry, $move) {
                    if (!isset($carry[$move->getFENReached()])) {
                        $carry[$move->getFENReached()] = [$move];
                    } else {
                        $carry[$move->getFENReached()][] = $move;
                    }
                    return $carry;
                }, []);
            case 'SAN':
                return array_reduce($moves, function ($carry, $move) {
                    if (!isset($carry[$move->getNotation()->getText()])) {
                        $carry[$move->getNotation()->getText()] = [$move];
                    } else {
                        $carry[$move->getNotation()->getText()][] = $move;
                    }
                    return $carry;
                }, []);
        }

        return $moves;
    }
}
