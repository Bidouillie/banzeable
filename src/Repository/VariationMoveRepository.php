<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\VariationMove;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VariationMove>
 */
class VariationMoveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VariationMove::class);
    }

    /**
     * @return VariationMove[]
     */
    public function findFromCourse(Course $course)
    {
        $qb = $this->createQueryBuilder('move')
            ->innerJoin('move.variation', 'variation')
            ->where('variation.course = :course');

        $qb->setParameter('course', $course);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return VariationMove[]
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
}
