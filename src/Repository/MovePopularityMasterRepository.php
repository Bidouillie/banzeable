<?php

namespace App\Repository;

use App\Entity\MovePopularityMaster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovePopularity>
 */
class MovePopularityMasterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovePopularityMaster::class);
    }

    // TODO search by whole id (variant, speeds...)
    public function findByFEN(string $FEN)
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.FEN = :FEN')
            ->setParameter('FEN', $FEN);

        return $qb->getQuery()->getResult();
    }
}
