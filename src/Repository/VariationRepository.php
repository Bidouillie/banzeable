<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Variation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Variation>
 */
class VariationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Variation::class);
    }

    /**
     * @return Variation[]
     */
    public function findByMoveFromCourse(string $FEN, string $SAN, Course $course)
    {
        $qb = $this->createQueryBuilder('variation')
            ->innerJoin('variation.moves', 'move')
            ->innerJoin('move.notation', 'notation')
            ->andWhere('variation.course = :course')
            ->andWhere('notation.FEN = :FEN')
            ->andWhere('notation.text = :SAN')
            ->setParameter('course', $course)
            ->setParameter('FEN', $FEN)
            ->setParameter('SAN', $SAN);

        $variations = $qb->getQuery()->getResult();

        return $variations;
    }
}
