<?php

namespace App\Service;

use App\Entity\Move;
use App\Entity\MovePopularity;
use App\Form\BuildMoveType;
use Symfony\Component\Form\FormFactoryInterface;

class MoveBuilderService
{
    public function __construct(
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
     * @param array<Move> $movesSaved
     * @param array<array<Move>> $movesSavedFENReached
     */
    public function buildMoveForm(MovePopularity $move, string $name, string $action, bool $myTurn, int $nbGames, array $FENHistory, string $FEN, array $LANHistory, ?string $LAN, array $selectedPercentHistory, array $totalSelectedPercentHistory, float $totalSelectedPercent, array $canSaveHistory, bool $canSave, array $movesMerged, array $lastMovesMerged, array $movesSaved, array $movesSavedFENReached)
    {
        $SAN = $move->getSan();
        $FENReached = $move->getNextFEN();

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
}
