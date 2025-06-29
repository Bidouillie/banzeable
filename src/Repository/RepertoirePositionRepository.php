<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\RepertoirePosition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RepertoirePosition>
 */
class RepertoirePositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RepertoirePosition::class);
    }

    /**
     * @return array<string,float>
     */
    public function findExpectedPercentageGroupedByFen(Course $course, array $fens)
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.fen, p.expectedPercentage')
            ->andWhere('p.course = :course')
            ->andWhere('p.fen IN (:fens)')
            ->setParameter('course', $course)
            ->setParameter('fens', $fens);

        $positions = $qb->getQuery()->getArrayResult();

        return array_reduce($positions, function ($carry, $position) {
            $carry[$position['fen']] = floatval($position['expectedPercentage']);
            return $carry;
        }, []);
    }

    /**
     * @return array<string,RepertoirePosition>
     */
    public function findByFenGrouped(Course $course, array $fens)
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.course = :course')
            ->andWhere('p.fen IN (:fens)')
            ->setParameter('course', $course)
            ->setParameter('fens', $fens);

        $positions = $qb->getQuery()->getResult();

        return array_reduce($positions, function ($carry, RepertoirePosition $position) {
            $carry[$position->getFen()] = $position;
            return $carry;
        }, []);
    }

    /**
     * @return array<string,RepertoirePosition>
     */
    public function findByFenGroupedOrdered(Course $course, array $fens)
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.course = :course')
            ->andWhere('p.fen IN (:fens)')
            ->setParameter('course', $course)
            ->setParameter('fens', $fens);

        /**
         * @var array<RepertoirePosition> $positions
         */
        $positions = $qb->getQuery()->getResult();

        usort($positions, function ($a, $b) use ($fens) {
            return array_search($b->getFen(), $fens) <=> array_search($a->getFen(), $fens);
        });

        return array_reduce($positions, function ($carry, RepertoirePosition $position) {
            $carry[$position->getFen()] = $position;
            return $carry;
        }, []);
    }
}
