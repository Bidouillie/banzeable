<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Notation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notation>
 */
class NotationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notation::class);
    }

    public function findByFEN(array $FENs)
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.FEN IN (:FENs)')
            ->setParameter('FENs', $FENs);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Notation[]
     */
    public function findByFENFromCourse(string|array $FEN, Course $course, string $byKey = null)
    {
        $qb = $this->createQueryBuilder('notation')
            ->innerJoin('notation.moves', 'moves')
            ->innerJoin('moves.variation', 'variation')
            ->where('variation.course = :course');

        if (is_array($FEN)) {
            $qb->andWhere('notation.FEN IN (:FEN)');
        } else {
            $qb->andWhere('notation.FEN = :FEN');
        }

        $qb->setParameter('course', $course)
            ->setParameter('FEN', $FEN);

        $notations = $qb->getQuery()->getResult();

        switch ($byKey) {
            case 'FEN':
                return array_reduce($notations, function ($carry, $notation) {
                    $carry[$notation->getFEN()] = $notation;
                    return $carry;
                }, []);
            case 'SAN':
                return array_reduce($notations, function ($carry, $notation) {
                    $carry[$notation->getText()] = $notation;
                    return $carry;
                }, []);
        }

        return $notations;
    }
}
