<?php

namespace App\EventListener;

use App\Entity\Course;
use App\Entity\Move;
use App\Repository\CourseRepository;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use App\Service\MoveLoaderService;
use Chess\FenToBoardFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class BuildMovesTerminateListener
{
    public function __construct(
        private readonly CourseRepository $courseRepo,
        private readonly MoveBuilderService $mbService,
        private readonly MoveLoaderService $mlService,
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMasterRepository $mpMasterRepo,
    ) {}

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->get('_route') === 'app_position_build_moves') {

            /**
             * @var Course $course
             */
            $course = $this->courseRepo->find($request->get('course'));
            $baseFen = $request->get('baseFen');

            $form = $request->get('build_moves');

            if (isset($form['moves'])) {
                $movesPlayed = $form['moves'];
                foreach ($movesPlayed as $key => $move) {
                    $moveObject = new Move();
                    $moveObject->setLan($move['lan']);
                    $movesPlayed[$key] = $moveObject;
                }

                $movesSavedByFen = $course->getRepertoireMovesByFen();

                $expectedPercentage = $this->mbService->populateMoves($course, $baseFen, $movesPlayed, $newMovesPlayed, $movePopularitiesByFenLan);

                if (!empty($newMovesPlayed)) {

                    $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                    if (isset($movePopularitiesByFenLan[$fen])) {
                        $movesPopularities = $movePopularitiesByFenLan[$fen]['moves'];
                        $nbGames = $movePopularitiesByFenLan[$fen]['nbGames'];
                    } else {
                        $movesPopularities = $this->mpRepo->findGroupedByLan($fen, $nbGames) ?? [];
                    }

                    if (!empty($movesPopularities)) {

                        $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                        $nbMastersGames = 0;
                        $movePopularitiesMasterByLan = $myTurn ? ($this->mpMasterRepo->findGroupedByLan($fen, $nbMastersGames) ?? []) : [];

                        $movesToPlay = $this->mbService->buildMoves($course, $fen, $movesSavedByFen, $movesPopularities, $movePopularitiesMasterByLan);
                        $fens = [];
                        foreach ($movesToPlay as $move) {
                            $board = FenToBoardFactory::create($fen);
                            $board->playLan($board->turn, $move->getLan());
                            $fens[] = $board->toFen();
                        }

                        $movesPopularitiesByFenLan = array_reduce($this->mpRepo->findByFenGrouped($fens), function ($carry, $fen) {
                            $carry[$fen] = $fen;
                            return $carry;
                        }, []);
                        $movesPopularitiesMasterByFenGrouped = array_reduce($this->mpMasterRepo->findByFenGrouped($fens), function ($carry, $fen) {
                            $carry[$fen] = $fen;
                            return $carry;
                        }, []);

                        $this->mlService->preloadMoves(array_map(function ($move) {
                            return $move->getFenTo();
                        }, array_filter($movesToPlay, function ($move) use ($expectedPercentage, $nbGames, $nbMastersGames, $course, $myTurn) {
                            return $myTurn ? ($move->getPopularityMaster() === null ? false : $move->getPopularityMaster()->getTotal() / $nbMastersGames > 1 / 100) : $expectedPercentage * $move->getPopularity()->getTotal() / $nbGames > 1 / $course->getCoverage();
                        })), $movesPopularitiesByFenLan, $movesPopularitiesMasterByFenGrouped);
                    }
                }
            }
        }
    }
}
