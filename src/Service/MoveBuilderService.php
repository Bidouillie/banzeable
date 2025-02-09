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
    public function __construct(
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMasterRepository $mpMasterRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @param Course $course
     * @param string $baseFen
     * @param array<Move> $movesPlayed
     */
    public function populateMoves(Course $course, string $baseFen, array &$movesPlayed)
    {
        $movesSavedByFen = $course->getRepertoireMovesByFen();
        $positionsSavedByFen = $course->getPositionsByFen();

        $positions = [];
        foreach ($positionsSavedByFen as $fen => $position) {
            $positions[$fen] = $position['position'];
        }

        foreach ($movesSavedByFen as $fenFrom => $movesSavedByFenTo) {
            foreach ($movesSavedByFenTo as $fenTo => $move) {
                $positionsSavedByFen[$fenFrom]['nextMoves'][] = $move;
                $positionsSavedByFen[$fenTo]['previousMoves'][] = $move;
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
                    $this->updateExpectedPercentageRecursive($positionReached, $positionsSavedByFen);
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

        return $newMovesPlayed;
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
            $movesPopularities = $this->mpRepo->findGroupedByLan($fen) ?? [];
        }
        if (!isset($movesPopularitiesMaster)) {
            $movesPopularitiesMaster = $this->mpMasterRepo->findGroupedByLan($fen) ?? [];
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

    /**
     * @param array{position:Position,previousMoves:Move[],nextMoves:Move[]} $position
     * @param array<string,array{position:Position,previousMoves:Move[],nextMoves:Move[]}> $positions
     */
    public function updateExpectedPercentageRecursive(array $position, array $positions)
    {
        if (count($position['previousMoves']) > 0) {
            $expectedPercentage = 0;
            foreach ($position['previousMoves'] as $move) {
                $expectedPercentage += $positions[$move->getFenFrom()]['position']->getExpectedPercentage() * $move->getSelectedPercentage();
            }
            $position['position']->setExpectedPercentage($expectedPercentage);
        }
        foreach ($position['nextMoves'] as $move) {
            self::updateExpectedPercentageRecursive($positions[$move->getFenTo()], $positions);
        }
    }
}
