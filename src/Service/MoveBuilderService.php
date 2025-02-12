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
use Doctrine\ORM\EntityManagerInterface;
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

    /**
     * @var array<string,string> $fens
     */
    private $fens;

    public function __construct(
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMasterRepository $mpMasterRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @param Course $course
     * @param string $baseFen
     * @param array<Move> $movesPlayed
     * @param array<Move> $newMovesPlayed
     * @param array<string,array{moves:array<string,MovePopularity>,nbGames:int}> $movePopularitiesByFenLan
     * 
     * @return Position
     */
    public function populateMoves(Course $course, string $baseFen, array &$movesPlayed, &$newMovesPlayed, &$movePopularitiesByFenLan = null)
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
        $board = FenToBoardFactory::create($baseFen);
        foreach ($movesPlayed as $key => $move) {
            $fenFrom = $board->toFen();
            if (!$board->playLan($board->turn, $move->getLan())) {
                throw new HttpException(403, "List of moves is not correct (from provided fen $baseFen)");
            }
            $fenTo = $board->toFen();

            if (isset($movesSavedByFen[$fenFrom][$fenTo])) {
                $movesPlayed[$key] = $movesSavedByFen[$fenFrom][$fenTo];
                continue;
            }

            if (!isset($basePosition)) {
                $keyBase = $key;
                $basePosition = $positions[$fenFrom];
            }

            $move->setCourse($course);

            $move->setFenFrom($fenFrom);

            if (!isset($positions[$fenTo])) {
                $position = new Position();
                $position->setCourse($course);
                $position->setFen($fenTo);

                $positions[$fenTo] = $position;

                $this->em->persist($position);
            }

            $move->setFenTo($fenTo);

            $this->em->persist($move);
        }

        if (!isset($basePosition)) {
            $basePosition = empty($movesPlayed) ? $positions[$baseFen] : $positions[end($movesPlayed)->getFenTo()];
        }
        $newMovesPlayed = isset($keyBase) ? array_slice($movesPlayed, $keyBase) : [];

        $fens = array_unique(array_merge(array_reduce($newMovesPlayed, function ($carry, $move) {
            $carry[] = $move->getFenFrom();
            $carry[] = $move->getFenTo();
            return $carry;
        }, []), [$basePosition->getFen()]));

        $movePopularitiesByFenLan = $this->mpRepo->findGroupedByFenLan($fens);

        /**
         * Expected percentage
         */
        $expectedPercentage = $basePosition->getExpectedPercentage();
        foreach ($newMovesPlayed as $move) {
            unset($positionReached);
            if (!isset($movesSavedByFen[$move->getFenFrom()][$move->getFenTo()])) {
                if ($move->isMyTurn()) {
                    $selectedPercentage = 1;
                } else {
                    $selectedPercentage = 0;
                    if (isset($movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()])) {
                        $selectedPercentage = $movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()]->getTotal() / $movePopularitiesByFenLan[$move->getFenFrom()]['nbGames'];
                    }
                }

                $positionReached = $positionsSavedByFen[$move->getFenTo()] ?? null;
                if (isset($positionReached)) {
                    $selectedPercentage += $positionReached['position']->getExpectedPercentage() / $expectedPercentage;

                    foreach ($positionReached['previousMoves'] as $moveBuffer) {
                        if ($moveBuffer !== $move) {
                            $moveBuffer->setSelectedPercentage($expectedPercentage * $selectedPercentage / $moveBuffer->getPositionFrom()->getExpectedPercentage());
                        }
                    }
                    $this->updateExpectedPercentage($positionsSavedByFen, $positionReached);
                }

                $move->setSelectedPercentage($selectedPercentage);
            }

            $expectedPercentage *= $move->getSelectedPercentage();

            $positions[$move->getFenTo()]->setExpectedPercentage($expectedPercentage);

            if (!isset($movesSavedByFen[$move->getFenFrom()][$move->getFenTo()])) {
                $positions[$move->getFenFrom()]->addNextMove($move);
                $positions[$move->getFenTo()]->addPreviousMove($move);
            }
        }

        $basePosition = empty($newMovesPlayed) ? (empty($movesPlayed) ? $positionsSavedByFen[$baseFen]['position'] : end($movesPlayed)->getPositionTo()) : end($newMovesPlayed)->getPositionTo();

        return $basePosition;
    }

    /**
     * @param Course $course
     * @param string $fen
     * @param array<string,MovePopularity>|null $movesPopularities
     * @param array<string,MovePopularityMaster>|null $movesPopularitiesMaster
     * @param array<string,array<string,Move>> $movesSavedByFen
     * 
     * @return Move[]
     */
    public function buildMoves(Course $course, string $fen, array $movesPopularities = null, array $movesPopularitiesMaster = null, array $movesSavedByFen)
    {
        if (!isset($movesPopularities)) {
            $movesPopularities = $this->mpRepo->findGroupedByLan($fen) ?? [];
        }

        $moves = [];
        foreach ($movesPopularities as $movePopularity) {
            $board = FenToBoardFactory::create($fen);
            $board->playLan($board->turn, $movePopularity->getLan());

            if (isset($movesSavedByFen[$fen][$board->toFen()])) {
                $move = $movesSavedByFen[$fen][$board->toFen()];
            } else {
                $move = new Move();
                $move->setCourse($course);
                $move->setFenFrom($fen);
                $move->setFenTo($board->toFen());
                $move->setLan($movePopularity->getLan());
            }

            $move->setPopularity($movePopularity);
            if (isset($movesPopularitiesMaster[$movePopularity->getLan()])) {
                $move->setPopularityMaster($movesPopularitiesMaster[$movePopularity->getLan()]);
            }
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
        if ($position['position']->isMyTurn()) {
            $completion = 0;
            foreach ($position['nextMoves'] as $move) {
                $completion += self::updateCompletionRecursive($this->positions[$move->getFenTo()]);
            }
            $myMoveCompletionPercentage = (1 / $this->course->getCoverage()) / max($position['position']->getExpectedPercentage(), 1 / $this->course->getCoverage());
            $completion /= count($position['nextMoves']);
            $completion += $myMoveCompletionPercentage - $myMoveCompletionPercentage * $completion;

            $position['position']->setCompletion($completion);
        } else {
            $completion = 0;
            $expectedPercentage = $position['position']->getExpectedPercentage();
            $nbGames = $this->movePopularitiesByFenLan[$position['position']->getFen()]['nbGames'];
            $nbMovesToCover = $nbGamesToCover = 0;
            foreach ($this->movePopularitiesByFenLan[$position['position']->getFen()]['moves'] as $move) {
                if ($expectedPercentage * $move->getTotal() / $nbGames > 1 / $this->course->getCoverage()) {
                    $nbMovesToCover++;
                    $nbGamesToCover += $move->getTotal();
                }
            }
            if ($nbMovesToCover > 0) {
                foreach ($position['nextMoves'] as $move) {
                    if (isset($this->movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()])) {
                        $nextPosition = $this->positions[$move->getFenTo()];
                        $completion += self::updateCompletionRecursive($nextPosition) * $this->movePopularitiesByFenLan[$move->getFenFrom()]['moves'][$move->getLan()]->getTotal();
                    }
                }
                $completion /= $nbGamesToCover;
            } else {
                $completion = 1;
            }

            $position['position']->setCompletion($completion);
        }

        return $position['position']->getCompletion();
    }

    /**
     * @param array<string,array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>}> $positions
     * @param array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    public function getFensOpponentTurn(array $positions, array $position)
    {
        $this->positions = $positions;
        $this->fens = [];
        $this->getFensOpponentTurnRecursive($position);
        return $this->fens;
    }

    /**
     * @param array{position:Position,previousMoves:array<string,Move>,nextMoves:array<string,Move>} $position
     */
    private function getFensOpponentTurnRecursive(array $position)
    {
        if (!$position['position']->isMyTurn()) {
            $this->fens[$position['position']->getFen()] = $position['position']->getFen();
        }
        foreach ($position['previousMoves'] as $move) {
            self::getFensOpponentTurnRecursive($this->positions[$move->getFenFrom()]);
        }
    }
}
