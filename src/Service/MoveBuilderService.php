<?php

namespace App\Service;

use App\DTO\MovePopularityWithTotalDTO;
use App\Entity\Course;
use App\Entity\Move;
use App\Entity\MovePopularity;
use App\Entity\RepertoirePosition;
use App\Helper\MoveStat;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use App\Repository\MoveRepository;
use App\Repository\PositionRepository;
use App\Repository\RepertoirePositionRepository;
use Chess\FenToBoardFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MoveBuilderService
{
    /**
     * @var Course $course
     */
    private $course;

    /**
     * @var array<string,array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $positions
     */
    private $positions;

    /**
     * @var array<string,false|array{nbGames:int,moves:array<string,MovePopularityWithTotalDTO>}> $movePopularitiesByFenLan
     */
    private $movePopularitiesByFenLan;

    public function __construct(
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMastersRepository $mpMastersRepo,
        private readonly MoveRepository $moveRepo,
        private readonly RepertoirePositionRepository $rPosRepo,
        private readonly PositionRepository $posRepo,
    ) {}

    /**
     * @param Course $course
     * @param bool $myTurnStart
     * @param Move[] $moves
     * @param float $expectedPercentage
     * @param bool $diverged
     * @param bool $merged
     * @param string[] $mpMissingFens
     * @param null|int $divergeIndex
     * @param null|int $mergeIndex
     */
    public function getExpectedPercentage(Course $course, bool $myTurnStart, array $moves, float $expectedPercentage, bool &$diverged, bool $merged, ?array &$mpMissingFens, ?int &$divergeIndex, ?int &$mergeIndex)
    {
        if (!$diverged) {
            $movesSaved = $this->moveRepo->findSavedGroupedByFenLan($course, array_map(function ($move) {
                return $move->getFenFrom();
            }, $moves));
        }

        $positions = $this->rPosRepo->findExpectedPercentageGroupedByFen($course, array_map(function ($move) {
            return $move->getFenTo();
        }, $moves));

        $expectedPercentages = [];
        $fens = [];
        $myTurn = $myTurnStart;
        foreach ($moves as $key => $move) {
            if (!$diverged) {
                if (!isset($movesSaved[$move->getFenFrom()][$move->getLan()])) {
                    $diverged = true;
                    $divergeIndex = $key;
                }
            }
            if (isset($positions[$move->getFenTo()])) {
                $expectedPercentages[$key] = $positions[$move->getFenTo()];
                if ($diverged && !$merged) {
                    $merged = true;
                    $mergeIndex = $key;
                }
            } else {
                $missing = true;
                if (!$myTurn) {
                    $fens[] = $move->getFenFrom();
                }
            }

            $myTurn = !$myTurn;
        }

        $mpMissingFens = [];

        if (isset($missing)) {
            $fens[] = end($moves)->getFenTo();
            $this->movePopularitiesByFenLan = $this->mpRepo->findGroupedByFenLan($fens);

            $myTurn = $myTurnStart;
            foreach ($moves as $key => $move) {
                if (isset($expectedPercentages[$key])) {
                    $expectedPercentage = $expectedPercentages[$key];
                } elseif ($myTurn) {
                    $expectedPercentages[$key] = $expectedPercentage;
                } elseif (!empty($this->movePopularitiesByFenLan[$move->getFenFrom()])) {
                    $expectedPercentage *= $this->movePopularitiesByFenLan[$move->getFenFrom()]['nbGames'] > 0 && isset($this->movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()]) ? ($this->movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()]->total / $this->movePopularitiesByFenLan[$move->getFenFrom()]['nbGames']) : 0;
                    $expectedPercentages[$key] = $expectedPercentage;
                } else {
                    $mpMissingFens[] = $move->getFenFrom();
                }

                $myTurn = !$myTurn;
            }
        }

        return count($mpMissingFens) > 0 ? null : array_values($expectedPercentages);
    }

    /**
     * @param Course $course
     * @param string $fen
     * @param bool $myturn
     * @param null|float $expectedPercentage
     * @param bool &$popularityLoaded
     * @param bool &$popularityMastersLoaded
     * @param int &$nbMovesSaved
     * @param string[] &$evalMissingFens
     */
    public function buildCandidateMoves(Course $course, string $fen, bool $myTurn, ?float $expectedPercentage, ?bool &$popularityLoaded, ?bool &$popularityMastersLoaded, ?int &$nbMovesSaved, ?array &$evalMissingFens)
    {
        $board = FenToBoardFactory::create($fen);
        $pieces = $board->pieces($board->turn);

        $fens = [];
        $candidates = [];
        foreach ($pieces as $piece) {
            foreach ($board->legal($piece->sq) as $sq) {
                $lan = $piece->sq . $sq;

                $board2 = FenToBoardFactory::create($fen);
                $board2->playLan($board2->turn, $lan);

                $fenTo = $board2->toFen();

                $fens[] = $fenTo;

                $candidates[] = [
                    'fenTo' => $fenTo,
                    'lan' => $lan,
                ];
            }
        }

        $movesPopularities = $this->movePopularitiesByFenLan[$fen] ?? $this->mpRepo->findWithTotalGroupedByLan($fen);

        if ($myTurn) {
            $movesPopularitiesMasters = $this->mpMastersRepo->findGroupedByLan($fen);
        }

        $popularityLoaded = !empty($movesPopularities);
        $popularityMastersLoaded = !empty($movesPopularitiesMasters);

        if (isset($expectedPercentage)) {
            $movesSavedByLan = $this->moveRepo->findGroupedByLan($course, $fen);
        }

        $repertoirePositions = $this->rPosRepo->findGroupedByFen($course, $fens);

        $positions = $this->posRepo->findEvaluationGroupedByFen($fens);

        $nbMovesSaved = 0;
        $evalMissingFens = [];
        $candidateMovestats = [];
        foreach ($candidates as $candidate) {

            $lan = $candidate['lan'];

            $selectedPercentage = empty($movesPopularities) ? null : ($movesPopularities['nbGames'] > 0 && isset($movesPopularities['moves'][$lan]) ? $movesPopularities['moves'][$lan]->total / $movesPopularities['nbGames'] : 0);

            $selectedPercentageMasters = empty($movesPopularitiesMasters) ? null : ($movesPopularitiesMasters['nbGames'] > 0 && isset($movesPopularitiesMasters['moves'][$lan]) ? $movesPopularitiesMasters['moves'][$lan]->total / $movesPopularitiesMasters['nbGames'] : 0);

            if (isset($movesSavedByLan[$lan])) {
                $move = $movesSavedByLan[$lan];
                $nbMovesSaved++;
            } else {
                $move = new Move();
                $move->setFenFrom($fen);
                $move->setFenTo($candidate['fenTo']);
                $move->setLan($lan);
            }

            if (isset($expectedPercentage) && ($myTurn || isset($selectedPercentage))) {
                $moveExpectedPercentage = $expectedPercentage * ($myTurn ? 1 : $selectedPercentage);
            }

            if (!isset($positions[$candidate['fenTo']]) || $positions[$candidate['fenTo']]->mate === 0) {
                $evalMissingFens[] = $candidate['fenTo'];
            }

            $moveStats = new MoveStat($move);
            $moveStats->saved = isset($movesSavedByLan[$lan]);
            $moveStats->popularity = $movesPopularities['moves'][$lan] ?? null;
            $moveStats->selectedPercentage = $selectedPercentage;
            $moveStats->popularityMasters = $movesPopularitiesMasters['moves'][$lan] ?? null;
            $moveStats->selectedPercentageMasters = $selectedPercentageMasters;
            $moveStats->expectedPercentage = isset($moveExpectedPercentage) ? number_format($moveExpectedPercentage, 9) : null;
            $moveStats->position = $repertoirePositions[$move->getFenTo()] ?? null;
            $moveStats->eval = isset($positions[$candidate['fenTo']]) && $positions[$candidate['fenTo']]->mate !== 0 ? $positions[$candidate['fenTo']]->mate ?? $positions[$candidate['fenTo']]->evaluation ?? null : null;
            $moveStats->mate = isset($positions[$candidate['fenTo']]->evaluation) || (isset($positions[$candidate['fenTo']]->mate) && $positions[$candidate['fenTo']]->mate !== 0) ? isset($positions[$candidate['fenTo']]->mate) : null;

            $candidateMovestats[$candidate['fenTo']] = $moveStats;
        }

        return $candidateMovestats;
    }

    /**
     * @param Course $course
     * @param string $baseFen
     * @param array<Move> &$movesPlayed
     * @param array<Move> &$newMovesPlayed
     * @param array<string,array{moves:array<string,MovePopularity>,nbGames:int}> &$movePopularitiesByFenLan
     * @param array<string,RepertoirePosition> &$rPositions
     * @param ?string|int &$mergeKey
     */
    public function populateMoves(Course $course, string $baseFen, array &$movesPlayed, ?array &$newMovesPlayed = null, ?array &$movePopularitiesByFenLan = null, ?array &$rPositions = null, mixed &$mergeKey = null)
    {
        $movesSavedByFen = $course->getRepertoireMovesByFen();
        $positionsSavedByFen = $course->getPositionsByFen();

        $rPositions = [];
        foreach ($positionsSavedByFen as $fen => $position) {
            $rPositions[$fen] = $position['position'];
        }

        foreach ($movesSavedByFen as $fenFrom => $movesSavedByFenTo) {
            foreach ($movesSavedByFenTo as $fenTo => $move) {
                $positionsSavedByFen[$fenFrom]['nextMoves'][$move->getLan()] = $move;
                $positionsSavedByFen[$fenTo]['previousMoves'][$move->getLan()] = $move;
            }
        }

        if (!isset($rPositions[$baseFen])) {
            throw new HttpException(404, "Base position not saved (from provided fen $baseFen)");
        }

        /**
         * Check moves are correct and populate them
         */
        $fens = [];
        foreach ($movesPlayed as $key => $move) {
            $fenFrom = $move->getFenFrom();
            $fenTo = $move->getFenTo();

            if (isset($movesSavedByFen[$fenFrom][$fenTo])) {
                $movesPlayed[$key] = $movesSavedByFen[$fenFrom][$fenTo];
                continue;
            }

            if (!isset($baseSavedPosition)) {
                $keyBase = $key;
                $baseSavedPosition = $rPositions[$fenFrom];
            }

            $move->setCourse($course);

            if (!isset($rPositions[$fenTo])) {
                $position = new RepertoirePosition();
                $position->setCourse($course);

                $rPositions[$fenTo] = $position;

                $fens[] = $fenTo;
            }
        }

        $positions = $this->posRepo->findGroupedByFen($fens);

        foreach ($rPositions as $fen => $rPosition) {
            if (isset($positions[$fen])) {
                $rPosition->setPosition($positions[$fen]);
            }
        }


        if (!isset($baseSavedPosition)) {
            $baseSavedPosition = $rPositions[empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo()];
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
        $mergeKey = null;
        $expectedPercentage = $baseSavedPosition->getExpectedPercentage();
        foreach ($newMovesPlayed as $key => $move) {
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
                        $selectedPercentage = $movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()]->total / $movePopularitiesByFenLan[$move->getFenFrom()]['nbGames'];
                    }
                }

                $positionReached = $positionsSavedByFen[$move->getFenTo()] ?? null;
                if (isset($positionReached)) {

                    $mergeKey = $mergeKey ?? $key;

                    $selectedPercentage += $positionReached['position']->getExpectedPercentage() / $expectedPercentage;

                    foreach ($positionReached['previousMoves'] as $moveBuffer) {
                        if ($moveBuffer !== $move) {
                            $moveBuffer->setSelectedPercentage($expectedPercentage * $selectedPercentage / $rPositions[$moveBuffer->getFenFrom()]->getExpectedPercentage());
                        }
                    }
                    $this->updateExpectedPercentage($positionsSavedByFen, $positionReached);
                }

                $move->setSelectedPercentage($selectedPercentage);
            }

            $expectedPercentage *= $move->getSelectedPercentage();

            $rPositions[$move->getFenTo()]->setExpectedPercentage($expectedPercentage);
        }

        $positionReached = $rPositions[empty($newMovesPlayed) ? (empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo()) : end($newMovesPlayed)->getFenTo()];

        return $positionReached->getExpectedPercentage();
    }

    /**
     * @param array<string,array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $positions
     * @param array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    public function updateExpectedPercentage(array $positions, array $position)
    {
        $this->positions = $positions;
        return $this->updateExpectedPercentageRecursive($position);
    }

    /**
     * @param array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
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
     * @param array<string,array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $positions
     * @param array<string,array{moves:array<string,MovePopularityWithTotalDTO>,nbGames:int}> $movePopularitiesByFenLan
     * @param array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    public function updateCompletion(Course $course, array $positions, array $movePopularitiesByFenLan, array $position)
    {
        $this->course = $course;
        $this->positions = $positions;
        $this->movePopularitiesByFenLan = $movePopularitiesByFenLan;
        return $this->updateCompletionRecursive($position);
    }

    /**
     * @param array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
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
            $completion /= count($nextMoves) > 0 ? count($nextMoves) : 1;
            $completion += $myMoveCompletionPercentage * (1 - $completion);
        } else {
            $expectedPercentage = $position->getExpectedPercentage();
            $nbMovesToCover = $nbGamesToCover = 0;

            $threshold = ($this->movePopularitiesByFenLan[$position->getFen()]['nbGames'] * $this->course->getTrueCoverage()) / $expectedPercentage;

            foreach ($this->movePopularitiesByFenLan[$position->getFen()]['moves'] as $move) {
                if ($move->total >= $threshold) {
                    $nbMovesToCover++;
                    $nbGamesToCover += $move->total;
                }
            }
            foreach ($nextMoves as $move) {
                $nextPosition = $this->positions[$move->getFenTo()];
                $nextPositionCompletion = self::updateCompletionRecursive($nextPosition);
                if ($this->movePopularitiesByFenLan[$position->getFen()]['moves'][$move->getLan()]->total >= $threshold) {
                    $completion += $nextPositionCompletion * $this->movePopularitiesByFenLan[$position->getFen()]['moves'][$move->getLan()]->total;
                }
            }
            $completion = $nbMovesToCover > 0 ? $completion / $nbGamesToCover : 1;
        }

        $position->setCompletion($completion);

        return $position->getCompletion();
    }
}
