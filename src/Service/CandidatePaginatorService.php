<?php

namespace App\Service;

use App\Entity\Course;
use App\Helper\MoveStat;

class CandidatePaginatorService
{
    private Course $course;

    private int $nbMovesToShow;

    private int $nbMovesShowned;

    public function __construct() {}

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function filterByEval(array &$moves)
    {
        $ca = $this->course->isBlackOrientation() ? -1 : 1;
        $cb = $this->course->isBlackOrientation() ? 1 : -1;

        uasort($moves, function ($a, $b) use ($ca, $cb) {
            if (isset($a->show) || isset($b->show)) {
                return isset($a->show) ? -1 : 1;
            }
            if (!isset($a->eval) || !isset($b->eval)) {
                return isset($a->eval) ? -1 : 1;
            }
            if ($a->mate xor $b->mate) {
                return ($a->mate ? $a->eval < 0 : $b->eval > 0) ? $ca : $cb;
            }
            return $a->eval < $b->eval ? $ca : $cb;
        });

        foreach ($moves as $key => $moveStat) {
            if (!$moveStat->show) {
                $moves[$key]->show = true;
                $this->nbMovesShowned++;
                if ($this->nbMovesShowned >= $this->nbMovesToShow) {
                    break;
                }
            }
        }
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> $moves
     */
    public function filterMyMoves(Course $course, int $nbMovesToShow, array &$moves, int $nbMovesSaved, bool $popularityMastersLoaded, bool $evalsLoaded)
    {
        $this->course = $course;
        $this->nbMovesToShow = $nbMovesToShow;
        $this->nbMovesShowned = 0;

        if ($nbMovesSaved > 0) {
            foreach ($moves as $key => $moveStat) {
                if ($moveStat->saved) {
                    $moves[$key]->show = true;
                    $this->nbMovesShowned++;
                }
            }
        } elseif ($popularityMastersLoaded) {
            foreach ($moves as $key => $moveStat) {
                if ($moveStat->selectedPercentageMasters > 1 / 100) {
                    $moves[$key]->show = true;
                    $this->nbMovesShowned++;
                }
            }
        }

        if ($nbMovesSaved <= 0 && $evalsLoaded && $this->nbMovesShowned < $this->nbMovesToShow) {

            $this->filterByEval($moves);
        }

        return $this->nbMovesShowned;
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> $moves
     */
    public function filterOppMoves(Course $course, int $nbMovesToShow, array &$moves, ?float $expectedPercentage, bool $evalsLoaded)
    {
        $this->course = $course;
        $this->nbMovesToShow = $nbMovesToShow;
        $this->nbMovesShowned = 0;

        if (isset($expectedPercentage) && $expectedPercentage > 0) {

            $threshold = $this->course->getTrueCoverage() / $expectedPercentage;

            foreach ($moves as $key => $moveStat) {
                if ($moveStat->selectedPercentage > $threshold) {
                    $moves[$key]->show = true;
                    $this->nbMovesShowned++;
                }
            }
        }

        if ($evalsLoaded && $this->nbMovesShowned < $this->nbMovesToShow) {

            $this->filterByEval($moves);
        }

        return $this->nbMovesShowned;
    }
}
