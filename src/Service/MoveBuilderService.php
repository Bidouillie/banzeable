<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Move;
use App\Entity\MovePopularity;
use App\Entity\MovePopularityMasters;
use App\Entity\Position;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use Chess\FenToBoardFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MoveBuilderService
{
    /**
     * @var Course $course
     */
    private $course;

    /**
     * @var array<string,array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $positions
     */
    private $positions;

    /**
     * @var array<string,array{moves:array<string,MovePopularity>,nbGames:int}> $movePopularitiesByFenLan
     */
    private $movePopularitiesByFenLan;

    public function __construct(
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMastersRepository $mpMastersRepo,
    ) {}

    /**
     * @param Course $course
     * @param string $baseFen
     * @param array<Move> $movesPlayed
     * @param array<Move> $newMovesPlayed
     * @param array<string,array{moves:array<string,MovePopularity>,nbGames:int}> $movePopularitiesByFenLan
     * @param array<string,Position> $positions
     */
    public function populateMoves(Course $course, string $baseFen, array &$movesPlayed, array &$newMovesPlayed = null, array &$movePopularitiesByFenLan = null, array &$positions = null)
    {
        $movesSavedByFen = $course->getRepertoireMovesByFen();
        $positionsSavedByFen = $course->getPositionsByFen();

        $positions = [];
        foreach ($positionsSavedByFen as $fen => $position) {
            $positions[$fen] = $position['position'];
        }

        foreach ($movesSavedByFen as $fenFrom => $movesSavedByFenTo) {
            foreach ($movesSavedByFenTo as $fenTo => $move) {
                $positionsSavedByFen[$fenFrom]['nextMoves'][$move->getLan()] = $move;
                $positionsSavedByFen[$fenTo]['previousMoves'][$move->getLan()] = $move;
            }
        }

        if (!isset($positions[$baseFen])) {
            throw new HttpException(404, "Base position not found (from provided fen $baseFen)");
        }

        /**
         * Check moves are correct and populate them
         */
        foreach ($movesPlayed as $key => $move) {
            $fenFrom = $move->getFenFrom();
            $fenTo = $move->getFenTo();

            if (isset($movesSavedByFen[$fenFrom][$fenTo])) {
                $movesPlayed[$key] = $movesSavedByFen[$fenFrom][$fenTo];
                continue;
            }

            if (!isset($baseSavedPosition)) {
                $keyBase = $key;
                $baseSavedPosition = $positions[$fenFrom];
            }

            $move->setCourse($course);

            if (!isset($positions[$fenTo])) {
                $position = new Position();
                $position->setCourse($course);
                $position->setFen($fenTo);

                $positions[$fenTo] = $position;
            }
        }

        if (!isset($baseSavedPosition)) {
            $baseSavedPosition = $positions[empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo()];
        }
        $newMovesPlayed = isset($keyBase) ? array_slice($movesPlayed, $keyBase) : [];

        $fens = array_unique(array_merge(array_reduce($newMovesPlayed, function ($carry, $move) {
            $carry[] = $move->getFenFrom();
            $carry[] = $move->getFenTo();
            return $carry;
        }, []), [$baseSavedPosition->getFen()]));

        $movePopularitiesByFenLan = $this->mpRepo->findGroupedByFenLan($fens);

        /**
         * Expected percentage
         */
        $expectedPercentage = $baseSavedPosition->getExpectedPercentage();
        foreach ($newMovesPlayed as $move) {
            unset($positionReached);
            if (!isset($movesSavedByFen[$move->getFenFrom()][$move->getFenTo()])) {
                if ($move->isMyTurn()) {
                    $selectedPercentage = 1;
                } else {
                    $selectedPercentage = 0;
                    if (empty($movePopularitiesByFenLan[$move->getFenFrom()])) {
                        return null;
                    }
                    if (isset($movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()])) {
                        $selectedPercentage = $movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()]->getTotal() / $movePopularitiesByFenLan[$move->getFenFrom()]['nbGames'];
                    }
                }

                $positionReached = $positionsSavedByFen[$move->getFenTo()] ?? null;
                if (isset($positionReached)) {
                    $selectedPercentage += $positionReached['position']->getExpectedPercentage() / $expectedPercentage;

                    foreach ($positionReached['previousMoves'] as $moveBuffer) {
                        if ($moveBuffer !== $move) {
                            $moveBuffer->setSelectedPercentage($expectedPercentage * $selectedPercentage / $positions[$moveBuffer->getFenFrom()]->getExpectedPercentage());
                        }
                    }
                    $this->updateExpectedPercentage($positionsSavedByFen, $positionReached);
                }

                $move->setSelectedPercentage($selectedPercentage);
            }

            $expectedPercentage *= $move->getSelectedPercentage();

            $positions[$move->getFenTo()]->setExpectedPercentage($expectedPercentage);
        }

        $positionReached = $positions[empty($newMovesPlayed) ? (empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo()) : end($newMovesPlayed)->getFenTo()];

        return $positionReached->getExpectedPercentage();
    }

    /**
     * @param Course $course
     * @param string $fen
     * @param array<string,array<string,Move>> $movesSavedByFen
     * @param null|false|array<string,MovePopularity> $movesPopularities
     * @param null|false|array<string,MovePopularityMasters> $movesPopularitiesMasters
     */
    public function buildCandidateMoves(Course $course, string $fen, array $movesSavedByFen, array $movesPopularities = null, array $movesPopularitiesMasters = null)
    {
        $board = FenToBoardFactory::create($fen);
        $pieces = $board->pieces($board->turn);

        $lans = [];
        foreach ($pieces as $piece) {
            foreach ($board->legal($piece->sq) as $sq) {
                $lans[] = $piece->sq . $sq;
            }
        }

        $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === $board->turn;

        $moves = [];

        foreach ($lans as $lan) {
            $board = FenToBoardFactory::create($fen);
            $board->playLan($board->turn, $lan);

            if (isset($movesSavedByFen[$fen][$board->toFen()])) {
                $move = $movesSavedByFen[$fen][$board->toFen()];
                if ($myTurn) {
                    $move->setPopularity($movesPopularities[$lan] ?? null);
                    $move->setPopularityMasters($movesPopularitiesMasters[$lan] ?? null);

                    return [$move];
                }
            } else {
                $move = new Move();
                $move->setCourse($course);
                $move->setFenFrom($fen);
                $move->setFenTo($board->toFen());
                $move->setLan($lan);
            }

            $move->setPopularity($movesPopularities[$lan] ?? null);
            $move->setPopularityMasters($movesPopularitiesMasters[$lan] ?? null);

            $moves[] = $move;
        }

        return $moves;
    }

    /**
     * @param array<string,array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $positions
     * @param array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    public function updateExpectedPercentage(array $positions, array $position)
    {
        $this->positions = $positions;
        return $this->updateExpectedPercentageRecursive($position);
    }

    /**
     * @param array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    private function updateExpectedPercentageRecursive(array $position)
    {
        if (count($position['previousMoves']) > 0) {
            $expectedPercentage = 0;
            foreach ($position['previousMoves'] as $move) {
                $expectedPercentage += $this->positions[$move->getFenFrom()]['position']->getExpectedPercentage() * $move->getSelectedPercentage();
            }
            $position['position']->setExpectedPercentage($expectedPercentage);
        }
        foreach ($position['nextMoves'] as $move) {
            self::updateExpectedPercentageRecursive($this->positions[$move->getFenTo()]);
        }
    }

    /**
     * @param Course $course
     * @param array<string,array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $positions
     * @param array<string,array{moves:array<string,MovePopularity>,nbGames:int}> $movePopularitiesByFenLan
     * @param array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    public function updateCompletion(Course $course, array $positions, array $movePopularitiesByFenLan, array $position)
    {
        $this->course = $course;
        $this->positions = $positions;
        $this->movePopularitiesByFenLan = $movePopularitiesByFenLan;
        return $this->updateCompletionRecursive($position);
    }

    /**
     * @param array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    private function updateCompletionRecursive(array $position)
    {
        $nextMoves = $position['nextMoves'];
        $position = $position['position'];

        $completion = 0;
        if ($position->isMyTurn()) {
            foreach ($nextMoves as $move) {
                $completion += self::updateCompletionRecursive($this->positions[$move->getFenTo()]);
            }
            $myMoveCompletionPercentage = $this->course->getTrueCoverage() / max($position->getExpectedPercentage(), $this->course->getTrueCoverage());
            $completion /= count($nextMoves);
            $completion += $myMoveCompletionPercentage - $myMoveCompletionPercentage * $completion;
        } else {
            $expectedPercentage = $position->getExpectedPercentage();
            $nbMovesToCover = $nbGamesToCover = 0;

            $threshold = ($this->movePopularitiesByFenLan[$position->getFen()]['nbGames'] * $this->course->getTrueCoverage()) / $expectedPercentage;

            foreach ($this->movePopularitiesByFenLan[$position->getFen()]['moves'] as $move) {
                if ($move->getTotal() >= $threshold) {
                    $nbMovesToCover++;
                    $nbGamesToCover += $move->getTotal();
                }
            }
            foreach ($nextMoves as $move) {
                $nextPosition = $this->positions[$move->getFenTo()];
                $nextPositionCompletion = self::updateCompletionRecursive($nextPosition);
                if ($this->movePopularitiesByFenLan[$position->getFen()]['moves'][$move->getLan()]->getTotal() >= $threshold) {
                    $completion += $nextPositionCompletion * $this->movePopularitiesByFenLan[$position->getFen()]['moves'][$move->getLan()]->getTotal();
                }
            }
            $completion = $nbMovesToCover > 0 ? $completion / $nbGamesToCover : 1;
        }

        $position->setCompletion($completion);

        return $position->getCompletion();
    }
}
