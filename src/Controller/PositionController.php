<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildLanMovesType;
use App\Message\PreloadMoves;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use App\Service\MoveLoaderService;
use Chess\FenToBoardFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function buildMoves(?Course $course, ?string $baseFen, Request $request, FormFactoryInterface $factory, MovePopularityRepository $mpRepo, MovePopularityMastersRepository $mpMastersRepo, MoveBuilderService $mbService, MoveLoaderService $mlService, MessageBusInterface $bus): Response
    {
        // TODO Check if the course is a repertoire
        $this->denyAccessUnlessGranted('course.owns', $course);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $factory->createNamed('build_moves_form', BuildLanMovesType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                /**
                 * @var array<Move> $movesPlayed
                 */
                $movesPlayed = $form->get('lanMoves')->getData();

                $movesSavedByFen = $course->getRepertoireMovesByFen();
                $positionsSavedByFen = $course->getPositionsByFen();

                $expectedPercentage = $mbService->populateMoves($course, $baseFen, $movesPlayed, $newMovesPlayed, $movePopularitiesByFenLan);

                $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                if (isset($movePopularitiesByFenLan[$fen])) {
                    $movesPopularitiesByLan = $movePopularitiesByFenLan[$fen]['moves'];
                    $nbGames = $movePopularitiesByFenLan[$fen]['nbGames'];
                } else {
                    $movesPopularitiesByLan = $mpRepo->findGroupedByLan($fen, $nbGames);
                }

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                $movePopularitiesMastersByLan = $myTurn ? ($mpMastersRepo->findGroupedByLan($fen, $nbMastersGames)) : null;

                $movesToPlay = $mbService->buildCandidateMoves($course, $fen, $movesSavedByFen, $movesPopularitiesByLan ?? [], $movePopularitiesMastersByLan ?? []);

                $movesForms = array_map(function ($move) use ($fen, $movesSavedByFen, $positionsSavedByFen) {
                    return [
                        'move' => $move,
                        'saved' => isset($movesSavedByFen[$fen][$move->getFenTo()]),
                        'next_saved' => isset($positionsSavedByFen[$move->getFenTo()]),
                    ];
                }, $movesToPlay);

                /**
                 * Preload moves
                 */
                if (isset($expectedPercentage)) {
                    if (($myTurn && !empty($movePopularitiesMastersByLan)) || (!$myTurn && !empty($movesPopularitiesByLan))) {
                        if ($myTurn) {
                            $movesToPreload = array_filter($movesToPlay, function ($move) use ($nbMastersGames) {
                                return $move->getPopularityMasters() !== null && $move->getPopularityMasters()->getTotal() / $nbMastersGames > 1 / 100;
                            });
                        } else {
                            $movesToPreload = array_filter($movesToPlay, function ($move) use ($expectedPercentage, $nbGames, $course) {
                                return $move->getPopularity() !== null && $expectedPercentage * $move->getPopularity()->getTotal() > $nbGames * $course->getTrueCoverage();
                            });
                        }

                        $fensToPreload = array_map(function ($move) {
                            return $move->getFenTo();
                        }, $movesToPreload);

                        $mlService->preloadMoves($fensToPreload, !$myTurn);

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

                return $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'nb_games' => $nbGames,
                    'nb_masters_games' => $nbMastersGames ?? null,
                    'expected_percentage' => $expectedPercentage,
                    'moves_forms' => $movesForms,
                    'can_save' => json_encode(!empty($newMovesPlayed)),
                    'moves_whole' => json_encode(!isset($message)),
                ]);
            }
        }

        return $this->render('position/index.html.twig', [
            'controller_name' => 'PositionController',
        ]);
    }
}
