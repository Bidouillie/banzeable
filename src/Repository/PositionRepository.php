<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * @return array<string,float>
     */
    public function findExpectedPercentageGroupedByFen(Course $course, string|array $fen)
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.fen, p.expected_percentage')
            ->andWhere('p.course = :course');

        if (is_array($fen)) {
            $qb->andWhere('p.fen IN (:fen)');
        } else {
            $qb->andWhere('p.fen = :fen');
        }

        $qb->setParameter('course', $course);
        $qb->setParameter('fen', $fen);

        $positions = $qb->getQuery()->getArrayResult();

        return array_reduce($positions, function ($carry, $position) {
            $carry[$position['fen']] = floatval($position['expected_percentage']);
            return $carry;
        }, []);
    }

    /**
     * @return array<string,float>
     */
    public function findCompletionGroupedByFen(Course $course, string|array $fen)
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.fen, p.completion')
            ->andWhere('p.course = :course');

        if (is_array($fen)) {
            $qb->andWhere('p.fen IN (:fen)');
        } else {
            $qb->andWhere('p.fen = :fen');
        }

        $qb->setParameter('course', $course);
        $qb->setParameter('fen', $fen);

        $positions = $qb->getQuery()->getArrayResult();

        return array_reduce($positions, function ($carry, $position) {
            $carry[$position['fen']] = floatval($position['completion']);
            return $carry;
        }, []);
    }
}
