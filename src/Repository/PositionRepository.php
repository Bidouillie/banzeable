<?php

namespace App\Repository;

use App\DTO\PositionEvaluationDTO;
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
     * @return Position|null
     */
    public function findOneBy(array $criteria, array|null $orderBy = null): object|null
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    public function findEvaluationGroupedByFen(array $fens)
    {
        $qb = $this->createQueryBuilder('p')
            ->select(sprintf('NEW %s(p.fen, p.evaluation, p.mate)', PositionEvaluationDTO::class))
            ->andWhere('p.fen in (:fens)')
            ->setParameter('fens', $fens);

        /**
         * @var PositionEvaluationDTO[] $positions
         */
        $positions = $qb->getQuery()->getResult();

        $postionsByFen = [];
        foreach ($positions as $position) {
            $postionsByFen[$position->fen] = $position;
        }

        return $postionsByFen;
    }

    /**
     * @return array<string,Position>
     */
    public function findGroupedByFen(array $fens)
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.fen in (:fens)')
            ->setParameter('fens', $fens);

        /**
         * @var Position[] $positions
         */
        $positions = $qb->getQuery()->getResult();

        $postionsByFen = [];
        foreach ($positions as $position) {
            $postionsByFen[$position->getFen()] = $position;
        }

        return $postionsByFen;
    }
}
