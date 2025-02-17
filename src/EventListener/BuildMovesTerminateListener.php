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

                $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                if ($myTurn) {
                    $movePopularitiesMasterByLan = $this->mpMasterRepo->findGroupedByLan($fen, $nbMastersGames);
                } else {
                    if (isset($movePopularitiesByFenLan[$fen])) {
                        $movesPopularitiesByLan = $movePopularitiesByFenLan[$fen]['moves'];
                        $nbGames = $movePopularitiesByFenLan[$fen]['nbGames'];
                    } else {
                        $movesPopularitiesByLan = $this->mpRepo->findGroupedByLan($fen, $nbGames) ?? [];
                    }
                }

                if (($myTurn && !empty($movePopularitiesMasterByLan)) || (!$myTurn && !empty($movesPopularitiesByLan))) {

                    $movesToPlay = $this->mbService->buildMoves($course, $fen, $movesSavedByFen, $movesPopularitiesByLan ?? [], $movePopularitiesMasterByLan ?? []);
                    $fens = [];
                    foreach ($movesToPlay as $move) {
                        $board = FenToBoardFactory::create($fen);
                        $board->playLan($board->turn, $move->getLan());
                        $fens[] = $board->toFen();
                    }

                    $movesPopularitiesByFenLan = $this->mpRepo->findByFenGrouped($fens);
                    $movesPopularitiesMasterByFenGrouped = $this->mpMasterRepo->findByFenGrouped($fens);

                    if ($myTurn) {
                        $movesToPreload = array_filter($movesToPlay, function ($move) use ($nbMastersGames) {
                            return $move->getPopularityMaster() !== null && $move->getPopularityMaster()->getTotal() / $nbMastersGames > 1 / 100;
                        });
                    } else {
                        $movesToPreload = array_filter($movesToPlay, function ($move) use ($expectedPercentage, $nbGames, $course) {
                            return $move->getPopularity() !== null && $expectedPercentage * $move->getPopularity()->getTotal() > $nbGames * $course->getTrueCoverage();
                        });
                    }

                    $fensToPreload = array_map(function ($move) {
                        return $move->getFenTo();
                    }, $movesToPreload);

                    $this->mlService->preloadMoves($fensToPreload, $movesPopularitiesByFenLan, $movesPopularitiesMasterByFenGrouped);
                } else {

                    $movesPopularitiesByFenLan = $this->mpRepo->findByFenGrouped($fen);
                    $movesPopularitiesMasterByFenGrouped = $this->mpMasterRepo->findByFenGrouped($fen);

                    if ($myTurn) {
                        if (!isset($movePopularitiesMasterByLan)) {
                            $this->mlService->preloadMoves($fen, $movesPopularitiesByFenLan, $movesPopularitiesMasterByFenGrouped);
                        }
                    } else {
                        if (!isset($movesPopularitiesByLan)) {
                            $this->mlService->preloadMoves($fen, $movesPopularitiesByFenLan, $movesPopularitiesMasterByFenGrouped);
                        }
                    }
                }
            }
        }
    }
}
