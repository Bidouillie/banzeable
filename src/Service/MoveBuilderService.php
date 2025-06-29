<?php

namespace App\Service;

use App\DTO\MovePopularityWithTotalDTO;
use App\Entity\Course;
use App\Entity\Move;
use App\Entity\MovePopularity;
use App\Entity\Position;
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
     * @var array<string,array{position:RepertoirePosition,saved:bool}> $reachedPositionsByFen
     */
    private $reachedPositionsByFen;

    /**
     * @var array<string,array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $nodedPositionsByFen
     */
    private $nodedPositionsByFen;

    /**
     * @var array<string,array<string,array{move:Move,saved:bool}>> $movesByFenLan
     */
    private $movesByFenLan;

    /**
     * @var array<string,false|array{nbGames:int,moves:array<string,MovePopularityWithTotalDTO>}> $movePopularitiesByFenLan
     */
    private $movePopularitiesByFenLan;

    private float $coverage;

    public function __construct(
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMastersRepository $mpMastersRepo,
        private readonly MoveRepository $moveRepo,
        private readonly RepertoirePositionRepository $rPosRepo,
        private readonly PositionRepository $posRepo,
    ) {
        $this->reachedPositionsByFen = [];
        $this->nodedPositionsByFen = [];
        $this->movesByFenLan = [];
    }

    /**
     * @var array<Move> $moves
     */
    private function addMovesToNodedPositions(array $moves)
    {
        foreach ($moves as $move) {
            if (!isset($this->nodedPositionsByFen[$move->getFenFrom()])) {
                $this->nodedPositionsByFen[$move->getFenFrom()] = [
                    'position' => $move->getPositionFrom(),
                    'previousMoves' => [],
                    'nextMoves' => [],
                ];
            }
            if (!isset($this->nodedPositionsByFen[$move->getFenTo()])) {
                $this->nodedPositionsByFen[$move->getFenTo()] = [
                    'position' => $move->getPositionTo(),
                    'previousMoves' => [],
                    'nextMoves' => [],
                ];
            }
            $this->nodedPositionsByFen[$move->getFenFrom()]['nextMoves'][$move->getLan()] = $move;
            $this->nodedPositionsByFen[$move->getFenTo()]['previousMoves'][$move->getLan()] = $move;
        }
    }

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

        $repertoirePositions = $this->rPosRepo->findByFenGrouped($course, $fens);

        $positions = $this->posRepo->findEvaluationGroupedByFen(array_merge([$fen], $fens));

        $nbMovesSaved = 0;
        $evalMissingFens = !isset($positions[$fen]) || $positions[$fen]->mate === 0 ? [$fen] : [];
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
     * @param array<Move> $movesPlayed
     */
    public function populateMoves(Course $course, array $movesPlayed)
    {
        // Ensure moves are provided
        $lastMove = end($movesPlayed);
        $firstMove = reset($movesPlayed);
        if ($firstMove === false || $lastMove === false) {
            throw new \Exception("No move provided");
        }

        $fens = [$firstMove->getFenFrom()];
        $fensFrom = [];
        foreach ($movesPlayed as $move) {
            $fens[] = $move->getFenTo();
            $fensFrom[] = $move->getFenFrom();
        }

        // Ensure moves do not loop
        if (count($fens) !== count(array_unique($fens))) {
            throw new \Exception("There is a loop in the moves played");
        }

        $movesByFenLan = $this->moveRepo->findGroupedByFenLan($course, $fensFrom);

        // Ensure the first and last moves are new ones
        if (isset($movesByFenLan[$firstMove->getFenFrom()][$firstMove->getLan()])) {
            throw new \Exception("First move already saved");
        }
        if (isset($movesByFenLan[$lastMove->getFenFrom()][$lastMove->getLan()])) {
            throw new \Exception("Last move already saved");
        }

        $rPositionsByFen = $this->rPosRepo->findByFenGroupedOrdered($course, $fens);

        $basePosition = $rPositionsByFen[$firstMove->getFenFrom()] ?? null;

        // Ensure moves start from a saved position
        if (!isset($basePosition)) {
            throw new \Exception(sprintf("Base position not saved (from fen %s)", $firstMove->getFenFrom()));
        }

        $this->reachedPositionsByFen[$firstMove->getFenFrom()] = [
            'position' => $basePosition,
            'saved' => true,
        ];

        foreach ($movesPlayed as $key => $move) {

            $this->movesByFenLan[$move->getFenFrom()][$move->getLan()] = [
                'move' => $move,
                'saved' => true,
            ];

            if (isset($movesByFenLan[$move->getFenFrom()][$move->getLan()])) {
                $movesPlayed[$key] = $movesByFenLan[$move->getFenFrom()][$move->getLan()];
                continue;
            }

            $this->movesByFenLan[$move->getFenFrom()][$move->getLan()]['saved'] = false;

            $move->setCourse($course);

            if (isset($rPositionsByFen[$move->getFenTo()])) {
                $rPosition = $rPositionsByFen[$move->getFenTo()];
            } else {
                $rPosition = new RepertoirePosition();
                $rPosition->setCourse($course);
                $rPosition->setFen($move->getFenTo());
            }

            $this->reachedPositionsByFen[$move->getFenTo()] = [
                'position' => $rPosition,
                'saved' => isset($rPositionsByFen[$move->getFenTo()]),
            ];

            $this->reachedPositionsByFen[$move->getFenFrom()]['position']->addNextMove($move);
            $rPosition->addPreviousMove($move);
        }

        $positions = $this->posRepo->findGroupedByFen($fens);

        foreach ($this->reachedPositionsByFen as $fen => $rPosition) {
            $rPosition['position']->setPosition($positions[$fen]);
        }

        return $movesPlayed;
    }

    /**
     * @param array<Move> $movesPlayed
     */
    public function setExpectedPercentage(array $movesPlayed)
    {
        // Ensure moves are provided
        $firstMove = reset($movesPlayed);
        if ($firstMove === false) {
            throw new \Exception("No move provided");
        }

        $basePosition = $firstMove->getPositionFrom();

        $gply = $basePosition->getGply();

        $fens = [$firstMove->getFenFrom()];
        $fensFrom = [];
        foreach ($movesPlayed as $move) {
            $fens[] = $move->getFenTo();
            $fensFrom[] = $move->getFenFrom();
        }

        // Ensure moves do not loop
        if (count($fens) !== count(array_unique($fens))) {
            throw new \Exception("There is a loop in the moves played");
        }

        $this->addMovesToNodedPositions($movesPlayed);

        $movePopularitiesByFenLan = $this->mpRepo->findGroupedByFenLan($fensFrom);

        $expectedPercentage = $basePosition->getExpectedPercentage() ?? 0.0;

        foreach ($movesPlayed as $key => $move) {

            if (!empty($this->movesByFenLan[$move->getFenFrom()][$move->getLan()]['saved'])) {

                $expectedPercentage *= $move->getSelectedPercentage();
                $this->reachedPositionsByFen[$move->getFenTo()]['position']->setExpectedPercentage($expectedPercentage);
                $this->reachedPositionsByFen[$move->getFenTo()]['position']->setGply($gply);
                continue;
            }

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

            if (!empty($this->reachedPositionsByFen[$move->getFenTo()]['saved'])) {

                $positionMerged = $this->reachedPositionsByFen[$move->getFenTo()]['position'];

                $mergeKey = $mergeKey ?? $key;

                var_dump($selectedPercentage);
                var_dump($positionMerged->getExpectedPercentage());
                var_dump($expectedPercentage);
                var_dump($selectedPercentage);

                $selectedPercentage += $positionMerged->getExpectedPercentage() / $expectedPercentage;

                var_dump($selectedPercentage);

                foreach ($positionMerged->getPreviousMoves() as $moveBuffer) {
                    if ($moveBuffer !== $move) {
                        $moveBuffer->setSelectedPercentage($expectedPercentage * $selectedPercentage / $moveBuffer->getPositionFrom()->getExpectedPercentage());
                    }
                }

                $moves = $this->moveRepo->findFromPositionGply($positionMerged);

                $gply = max($gply, $positionMerged->getGply() - 1);

                $this->addMovesToNodedPositions($moves);

                $this->updateExpectedPercentage($this->nodedPositionsByFen[$move->getFenTo()], $gply);
            }

            $move->setSelectedPercentage($selectedPercentage);

            $expectedPercentage *= $move->getSelectedPercentage();

            $this->reachedPositionsByFen[$move->getFenTo()]['position']->setExpectedPercentage($expectedPercentage);
            $this->reachedPositionsByFen[$move->getFenTo()]['position']->setGply($gply);

            $gply++;
        }

        return $mergeKey ?? null;
    }

    /**
     * @param array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    public function updateExpectedPercentage(array $position, int $gply)
    {
        return $this->updateExpectedPercentageRecursive($position, $gply);
    }

    /**
     * @param array{position:RepertoirePosition,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    private function updateExpectedPercentageRecursive(array $position, int $gply)
    {
        $expectedPercentage = 0;
        foreach ($position['previousMoves'] as $move) {
            $expectedPercentage += $this->nodedPositionsByFen[$move->getFenFrom()]['position']->getExpectedPercentage() * $move->getSelectedPercentage();
            $gply = max($gply, $move->getPositionFrom()->getGply());
        }

        $position['position']->setExpectedPercentage($expectedPercentage);
        $position['position']->setGply($gply + 1);

        foreach ($position['nextMoves'] as $move) {
            self::updateExpectedPercentageRecursive($this->nodedPositionsByFen[$move->getFenTo()], $gply + 1);
        }
    }

    public function updateCompletion(string $fen, float $coverage)
    {
        $this->coverage = $coverage;
        $this->movePopularitiesByFenLan = $this->mpRepo->findGroupedByFenLan(array_keys($this->nodedPositionsByFen));
        return $this->updateCompletionRecursive($this->nodedPositionsByFen[$fen]);
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
                $completion += self::updateCompletionRecursive($this->nodedPositionsByFen[$move->getFenTo()]);
            }
            $myMoveCompletionPercentage = $this->coverage / max($position->getExpectedPercentage(), $this->coverage);
            $completion /= count($nextMoves) > 0 ? count($nextMoves) : 1;
            $completion += $myMoveCompletionPercentage * (1 - $completion);
        } else {

            $expectedPercentage = $position->getExpectedPercentage();
            $nbMovesToCover = $nbGamesToCover = 0;

            $threshold = ($this->movePopularitiesByFenLan[$position->getFen()]['nbGames'] * $this->coverage) / $expectedPercentage;

            foreach ($this->movePopularitiesByFenLan[$position->getFen()]['moves'] as $move) {
                if ($move->total >= $threshold) {
                    $nbMovesToCover++;
                    $nbGamesToCover += $move->total;
                }
            }

            foreach ($nextMoves as $move) {
                $nextPositionCompletion = self::updateCompletionRecursive($this->nodedPositionsByFen[$move->getFenTo()]);
                if ($this->movePopularitiesByFenLan[$position->getFen()]['moves'][$move->getLan()]->total >= $threshold) {
                    $completion += $nextPositionCompletion * $this->movePopularitiesByFenLan[$position->getFen()]['moves'][$move->getLan()]->total;
                }
            }

            $completion = $nbMovesToCover > 0 ? $completion / $nbGamesToCover : 1;
        }

        $position->setCompletion(min($completion, 1));

        return $position->getCompletion();
    }
}
