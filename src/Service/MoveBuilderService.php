<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Move;
use App\Entity\MovePopularity;
use App\Entity\MovePopularityMaster;
use App\Entity\Position;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use Chess\FenToBoardFactory;

class MoveBuilderService
{
    public function __construct(
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMasterRepository $mpMasterRepo,
    ) {}

    /**
     * @param  Course $course
     * @param  string $fen
     * @param  array<string,MovePopularity>|null $movesPopularities
     * @param  array<string,MovePopularityMaster>|null $movesPopularitiesMaster
     * 
     * @return Move[]
     */
    public function buildMoves(Course $course, string $fen, array $movesPopularities = null, array $movesPopularitiesMaster = null)
    {
        if (!isset($movesPopularities)) {
            $movesPopularities = $this->mpRepo->findGroupedByLan($fen);
        }
        if (!isset($movesPopularitiesMaster)) {
            $movesPopularitiesMaster = $this->mpMasterRepo->findGroupedByLan($fen);
        }

        $moves = [];
        foreach ($movesPopularities as $movePopularity) {
            $board = FenToBoardFactory::create($fen);
            $board->playLan($board->turn, $movePopularity->getLan());

            $move = new Move();
            $move->setCourse($course);
            $move->setFenFrom($fen);
            $move->setFenTo($board->toFen());
            $move->setLan($movePopularity->getLan());
            $move->setPopularity($movePopularity);
            if (isset($movesPopularitiesMaster[$movePopularity->getLan()])) {
                $move->setPopularityMaster($movesPopularitiesMaster[$movePopularity->getLan()]);
            }
            $moves[] = $move;
        }

        return $moves;
    }

    public function updateExpectedPercentageRecursiveBack(Position $position)
    {
        if (count($position->getPreviousMoves()) < 1) {
            $expectedPercentage = $position->getExpectedPercentage();
        } else {
            $expectedPercentage = 0;
            foreach ($position->getPreviousMoves() as $move) {
                $expectedPercentage += self::updateExpectedPercentageRecursiveBack($move->getPositionFrom()) * $move->getSelectedPercentage();
            }
        }
        $position->setExpectedPercentage($expectedPercentage);
        return $position->getExpectedPercentage();
    }

    /**
     * @param array{position:Position,previousMoves:Move[],nextMoves:Move[]} $position
     * @param array<string,array{position:Position,previousMoves:Move[],nextMoves:Move[]}> $positions
     */
    public function updateExpectedPercentageRecursiveFront(array $position, array $positions)
    {
        if (count($position['previousMoves']) > 0) {
            $expectedPercentage = 0;
            foreach ($position['previousMoves'] as $move) {
                $expectedPercentage += $positions[$move->getFenFrom()]['position']->getExpectedPercentage() * $move->getSelectedPercentage();
            }
            $position['position']->setExpectedPercentage($expectedPercentage);
        }
        foreach ($position['nextMoves'] as $move) {
            self::updateExpectedPercentageRecursiveFront($positions[$move->getFenTo()], $positions);
        }
    }
}
