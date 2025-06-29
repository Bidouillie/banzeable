<?php

namespace App\Repository;

use App\Entity\AnkiNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnkiNote>
 */
class AnkiNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnkiNote::class);
    }

    public function deleteAll()
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->getQuery()
            ->execute();
    }

    public function findBaseByGuidWithExtra($type = 1)
    {
        $notes = $this->createQueryBuilder('note')->select('note.guid, note.ankiKey, note_extra.ankiKey AS extraAnkiKey, note_extra.json')
            ->leftJoin(AnkiNote::class, 'note_extra', Join::WITH, "note.ankiKey LIKE CONCAT('%', note_extra.ankiKey, '%')")
            ->andWhere('note.extra = FALSE')
            ->andWhere('note_extra.extra = TRUE')
            ->getQuery()
            ->getResult();

        $notesByGuid = [];
        foreach ($notes as $note) {
            if (!isset($notesByGuid[$note['guid']])) {
                $notesByGuid[$note['guid']] = ['extra' => []];
            }
            $notesByGuid[$note['guid']]['ankiKey'] = $note['ankiKey'];
            $notesByGuid[$note['guid']]['extra'][$note['extraAnkiKey']] = json_decode($note['json'])[0];
        }

        switch ($type) {
            case 0:
                foreach ($notesByGuid as $key => $note) {

                    $kanjiString = array_filter(mb_str_split($note['ankiKey']), function ($char) {
                        return preg_match('/[\x{4E00}-\x{9FBF}]/u', $char) > 0;
                    });

                    $notesByGuid[$key] = implode(' ', array_map(function ($char) use ($note) {
                        $level = $note['extra'][$char] ?? '?';
                        if (!preg_match('/^([0-9]+|\?)$/', $level)) {
                            dd($level);
                        }
                        return $char . '[' . $level . ']';
                    }, $kanjiString));
                }
                break;
            case 1:

                $notesByGuid = array_filter($notesByGuid, function ($note) {
                    $chars = mb_str_split($note['ankiKey']);
                    $kanjiCount = array_reduce($chars, function ($carry, $char) {
                        return preg_match('/[\x{4E00}-\x{9FBF}]/u', $char) > 0 ? ++$carry : $carry;
                    }, 0);
                    return $kanjiCount === 1 && count($note['extra']) === 1;
                });
                $notesByGuid = array_map(function ($note) {
                    return reset($note['extra']);
                }, $notesByGuid);
                break;
            default:
                return [];
        }

        return $notesByGuid;
    }

    //    /**
    //     * @return AnkiNote[] Returns an array of AnkiNote objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?AnkiNote
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
