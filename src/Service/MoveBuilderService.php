<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Move;
use App\Entity\VariationMove;
use App\Entity\MovePopularity;
use App\Entity\MovePopularityMaster;
use App\Entity\Position;
use App\Form\BuildMoveType;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use Symfony\Component\Form\FormFactoryInterface;

class MoveBuilderService
{
    public function __construct(
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMasterRepository $mpMasterRepo,
        private readonly FormFactoryInterface $formFactory,
    ) {}

    /**
     * @param MovePopularity $move
     * @param string $name
     * @param string $action
     * @param bool $myTurn
     * @param int $nbGames
     * @param array<string> $FENHistory
     * @param string $FEN
     * @param array<string> $LANHistory
     * @param ?string $LAN
     * @param array<float> $selectedPercentHistory
     * @param array<float> $totalSelectedPercentHistory
     * @param float $totalSelectedPercent
     * @param array<bool> $canSaveHistory
     * @param bool $canSave
     * @param array $movesMerged
     * @param array $lastMovesMerged
     * @param array<VariationMove> $movesSaved
     * @param array<array<VariationMove>> $movesSavedFENReached
     */
    public function buildMoveForm(MovePopularity $move, string $name, string $action, bool $myTurn, int $nbGames, array $FENHistory, string $FEN, array $LANHistory, ?string $LAN, array $selectedPercentHistory, array $totalSelectedPercentHistory, float $totalSelectedPercent, array $canSaveHistory, bool $canSave, array $movesMerged, array $lastMovesMerged, array $movesSaved, array $movesSavedFENReached)
    {
        $SAN = $move->getSan();
        $FENReached = $move->getNextFen();

        $moveSelectedPercent = isset($movesSaved[$SAN]) ? $movesSaved[$SAN]->getSelectedMultiplier() : ($myTurn ? 1 : $move->getTotal() / $nbGames);

        $moveTotalSelectedPercent = $totalSelectedPercent * $moveSelectedPercent;

        if (!isset($movesSaved[$SAN]) && isset($movesSavedFENReached[$FENReached])) {
            $moveToMerge = $movesSavedFENReached[$FENReached][0];
            if (isset($lastMovesMerged[$moveToMerge->getVariation()->getId()])) {
                $lastMoveMerged = $lastMovesMerged[$moveToMerge->getVariation()->getId()];
                $moveTotalSelectedPercent += $moveToMerge->getTotalSelectedMultiplier() * $totalSelectedPercentHistory[$lastMoveMerged['index']] / $lastMoveMerged['move']->getTotalSelectedMultiplier();
            } else {
                $moveTotalSelectedPercent += $moveToMerge->getTotalSelectedMultiplier();
            }
            $movesMerged[] = ['move' => $moveToMerge, 'index' => count($selectedPercentHistory)];
        }

        $moveSelectedPercent = $moveTotalSelectedPercent / $totalSelectedPercent;

        return $this->formFactory->createNamed($name, BuildMoveType::class, [
            'FENHistory' => [...$FENHistory, $FEN],
            'LANHistory' => [...$LANHistory, $LAN],
            'selectedPercentHistory' => [...$selectedPercentHistory, $moveSelectedPercent],
            'movesMerged' => $movesMerged,
            'canSaveHistory' => [...$canSaveHistory, $canSave],
        ], [
            'action' => $action,
        ]);
    }

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
            $movesPopularities = $this->mpRepo->findByFenLan($fen);
        }
        if (!isset($movesPopularitiesMaster)) {
            $movesPopularitiesMaster = $this->mpMasterRepo->findByFenLan($fen);
        }

        $moves = [];
        foreach ($movesPopularities as $movePopularity) {
            $move = new Move();
            $move->setCourse($course);
            $move->setFenFrom($fen);
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
