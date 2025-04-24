<?php

namespace App\Service;

use App\Entity\Course;
use App\Helper\MoveStat;

class CandidatePaginatorService
{
    private int $nbMovesToShow;

    private int $nbMovesShowned;

    public function __construct() {}

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function filterByShown(array &$moves)
    {
        foreach ($moves as $key => $moveStat) {
            if ($this->nbMovesShowned >= $this->nbMovesToShow) {
                break;
            }
            if (!$moveStat->show) {
                $moves[$key]->show = true;
                $this->nbMovesShowned++;
            }
        }
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function filterBySaved(array &$moves)
    {
        uasort($moves, function ($a, $b) {
            return $b->saved <=> $a->saved;
        });

        foreach ($moves as $key => $moveStat) {
            if (!$moveStat->saved) {
                break;
            }
            if (!$moveStat->show) {
                $moves[$key]->show = true;
                $this->nbMovesShowned++;
            }
        }
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function sortByEval(array &$moves, bool $isBlack)
    {
        $ca = $isBlack ? -1 : 1;
        $cb = $isBlack ? 1 : -1;

        uasort($moves, function ($a, $b) use ($ca, $cb) {
            if ($a->show || $b->show) {
                return $b->show <=> $a->show;
            }
            if (!isset($a->eval) || !isset($b->eval)) {
                return isset($a->eval) ? -1 : 1;
            }
            if ($a->mate xor $b->mate) {
                return ($a->mate ? $a->eval < 0 : $b->eval > 0) ? $ca : $cb;
            }
            return $a->eval < $b->eval ? $ca : $cb;
        });
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function sortByPopularity(array &$moves)
    {
        uasort($moves, function ($a, $b) {
            return $b->selectedPercentage <=> $a->selectedPercentage;
        });
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function filterByPopularity(array &$moves, float $threshold)
    {
        $this->sortByPopularity($moves);

        foreach ($moves as $key => $moveStat) {
            if ($moveStat->selectedPercentage <= $threshold) {
                break;
            }
            if (!$moveStat->show) {
                $moves[$key]->show = true;
                $this->nbMovesShowned++;
            }
        }
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function filterByPopularityMasters(array &$moves, float $threshold)
    {
        uasort($moves, function ($a, $b) {
            return $b->selectedPercentageMasters <=> $a->selectedPercentageMasters;
        });

        foreach ($moves as $key => $moveStat) {
            if ($moveStat->selectedPercentageMasters <= $threshold) {
                break;
            }
            if (!$moveStat->show) {
                $moves[$key]->show = true;
                $this->nbMovesShowned++;
            }
        }
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> &$moves
     */
    private function sortByPopularityWinRate(array &$moves, bool $isBlack)
    {
        $color = $isBlack ? 'black' : 'white';

        uasort($moves, function ($a, $b) use ($color) {
            if (!isset($a->popularity) || !isset($b->popularity)) {
                return isset($b->popularity) <=> false;
            }
            $pwrA = 2 * $a->popularity->$color + $a->popularity->draws;
            $pwrB = 2 * $b->popularity->$color + $b->popularity->draws;
            return $pwrB <=> $pwrA;
        });
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> $moves
     */
    public function filterMyMoves(array &$moves, Course $course, int $nbMovesToShow, bool $movesSaved, bool $popularityLoaded, bool $popularityMastersLoaded, bool $evalsLoaded)
    {
        $this->nbMovesToShow = $nbMovesToShow;
        $this->nbMovesShowned = 0;

        $evalsNeeded = false;

        if ($movesSaved) {
            $this->filterBySaved($moves);
        } else {
            if ($popularityMastersLoaded) {
                $this->filterByPopularityMasters($moves, 1 / 100);
            }

            if ($this->nbMovesShowned < $this->nbMovesToShow) {

                if ($evalsLoaded) {
                    $this->sortByEval($moves, $course->isBlackOrientation());
                } else {
                    $evalsNeeded = true;
                    if ($popularityLoaded) {
                        $this->sortByPopularityWinRate($moves, $course->isBlackOrientation());
                    }
                }

                $this->filterByShown($moves);
            }
        }

        return $evalsNeeded;
    }

    /**
     * @template TKey of int|string
     * 
     * @param array<TKey,MoveStat> $moves
     */
    public function filterOppMoves(Course $course, array &$moves, float $expectedPercentage)
    {
        $this->nbMovesShowned = 0;

        if ($expectedPercentage > 0) {

            $this->filterByPopularity($moves, $course->getTrueCoverage() / $expectedPercentage);
        } else {

            $this->sortByPopularity($moves);
        }
    }
}
