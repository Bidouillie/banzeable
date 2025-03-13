<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildLanMovesType;
use App\Message\PreloadMoves;
use App\Repository\MoveRepository;
use App\Repository\PositionRepository;
use App\Service\MoveBuilderService;
use App\Service\MoveLoaderService;
use Chess\FenToBoardFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/position')]
class PositionController extends AbstractController
{
    #[Route('/index', name: 'app_position')]
    public function index(): Response
    {
        return $this->render('position/index.html.twig', [
            'controller_name' => 'PositionController',
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/build-moves/{course}/{baseFen}', name: 'app_position_build_moves', requirements: ['course' => '\d+', 'baseFen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function buildMoves(?Course $course, ?string $baseFen, Request $request, FormFactoryInterface $factory, MoveRepository $moveRepo, PositionRepository $positionRepo, MoveBuilderService $mbService, MoveLoaderService $mlService, MessageBusInterface $bus): Response
    {
        // TODO Check if the course is a repertoire
        $this->denyAccessUnlessGranted('course.owns', $course);

        sleep(1);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $factory->createNamed('build_moves_form', BuildLanMovesType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                /**
                 * @var array<Move> $movesPlayed
                 */
                $movesPlayed = $form->get('lanMoves')->getData();

                $expectedPercentage = floatval($form->get('expectedPercentage')->getData() ?? 1);
                $missingPercentages = intval($form->get('missingPercentages')->getData() ?? 0);

                $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                // TODO check if form data is consistent

                if ($missingPercentages > 0) {

                    if ($missingPercentages > 5) {
                        throw new HttpException(425, "Too many requests");
                    }

                    $moves = array_slice($movesPlayed, -$missingPercentages);

                    $startingTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create(reset($moves)->getFenFrom())->turn;

                    $expectedPercentages = $mbService->getExpectedPercentage($course, $startingTurn, $moves, $expectedPercentage);

                    $expectedPercentage = empty($expectedPercentages) ? null : end($expectedPercentages);

                    var_dump($expectedPercentages);
                }

                if (isset($expectedPercentage)) {
                    $movesSavedByLan = $moveRepo->findGroupedByLan($course, $fen);

                    if (count($movesSavedByLan) > 0) {
                        $positionsReached = $positionRepo->findGroupedByFen($course, array_map(function ($move) {
                            return $move->getFenTo();
                        }, $movesSavedByLan));
                    }
                }

                $movesToPlay = $mbService->buildCandidateMoves($fen, $myTurn, $expectedPercentage, $movesSavedByLan ?? null, $positionsReached ?? null, $myTurn, $saved);

                if ($myTurn) {
                    foreach ($movesToPlay as $key => $movestat) {
                        $movesToPlay[$key]['show'] = ($saved && $movestat['saved']) || (!$saved && isset($movestat['selected_masters']) && $movestat['selected_masters'] > 1 / 100);
                    }
                } else {
                    $threshold = $course->getTrueCoverage() / $expectedPercentage;

                    foreach ($movesToPlay as $key => $movestat) {
                        $movesToPlay[$key]['show'] = isset($movestat['selected']) && $movestat['selected'] > $threshold;
                    }
                }

                /**
                 * Preload moves
                 */
                /*
                if (isset($expectedPercentage)) {
                    if (($myTurn && !empty($movePopularitiesMastersByLan)) || (!$myTurn && !empty($movesPopularitiesByLan))) {
                        if ($myTurn) {
                            $movesToPreload = array_filter($movesToPlay, function ($move) use ($nbMastersGames) {
                                return $move->getPopularityMasters() !== null && $move->getPopularityMasters()->getTotal() / $nbMastersGames > 1 / 100;
                            });
                        } else {
                            $movesToPreload = $movesToPlay;
                        }

                        $fensToPreload = array_map(function ($move) {
                            return $move->getFenTo();
                        }, array_filter($movesToPreload, function ($move) use ($movesSavedByFen, $positionsSavedByFen) {
                            return !isset($movesSavedByFen[$move->getFenFrom()][$move->getFenTo()]) && !isset($positionsSavedByFen[$move->getFenTo()]);
                        }));

                        if (count($fensToPreload) > 0) {
                            // TODO Save if fens have been preloaded in Position?
                            $mlService->preloadMoves($fensToPreload, !$myTurn);
                        }

                        if (!isset($movesPopularitiesByLan)) {
                            $mlService->preloadMoves($fen, false);
                            $message = new PreloadMoves($course->getId(), $baseFen, implode(' ', array_map(function ($move) {
                                return $move->getLan();
                            }, $movesPlayed)));
                        }
                    } elseif (!isset($movesPopularitiesByLan) || ($myTurn && !isset($movePopularitiesMastersByLan))) {
                        $mlService->preloadMoves($fen, $myTurn);
                        $message = new PreloadMoves($course->getId(), $baseFen, implode(' ', array_map(function ($move) {
                            return $move->getLan();
                        }, $movesPlayed)));
                    }
                } else {
                    $message = new PreloadMoves($course->getId(), $baseFen, implode(' ', array_map(function ($move) {
                        return $move->getLan();
                    }, $movesPlayed)));
                }

                if (isset($message)) {
                    $bus->dispatch($message);
                }
                */

                return $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'moves_forms' => $movesToPlay,
                    'can_save' => !empty($newMovesPlayed),
                    'moves_whole' => !isset($message),
                    'missing_percentages' => $expectedPercentages ?? [],
                ]);
            }
        }

        return $this->render('position/index.html.twig', [
            'controller_name' => 'PositionController',
        ]);
    }
}
