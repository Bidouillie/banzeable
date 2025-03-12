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
     * @return array<string,Position>
     */
    public function findGroupedByFen(Course $course, string|array $fen)
    {
        $qb = $this->createQueryBuilder('position')
            ->andWhere('position.course = :course');

        if (is_array($fen)) {
            $qb->andWhere('position.fen IN (:fen)');
        } else {
            $qb->andWhere('position.fen = :fen');
        }

        $qb->setParameter('course', $course);
        $qb->setParameter('fen', $fen);

        /**
         * @var Position[] $positions
         */
        $positions = $qb->getQuery()->getResult();

        return array_reduce($positions, function ($carry, $position) {
            $carry[$position->getFen()] = $position;
            return $carry;
        }, []);
    }
}
