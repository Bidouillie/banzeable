<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildMovesType;
use App\Message\PreloadMoves;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use App\Service\MoveLoaderService;
use Chess\FenToBoardFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function buildMoves(?Course $course, ?string $baseFen, Request $request, MovePopularityRepository $mpRepo, MovePopularityMastersRepository $mpMastersRepo, MoveBuilderService $mbService, MoveLoaderService $mlService, MessageBusInterface $bus): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $this->createForm(BuildMovesType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                /**
                 * @var array<Move> $movesPlayed
                 */
                $movesPlayed = $form->get('moves')->getData();

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
                 * Previous form
                 */
                if (!empty($movesPlayed)) {
                    $previousMovesPlayed = $movesPlayed;
                    array_pop($previousMovesPlayed);
                    $previousForm = $this->createForm(BuildMovesType::class, ['moves' => [...$previousMovesPlayed]], [
                        'action' => $this->generateUrl('app_position_build_moves', ['course' => $course->getId(), 'baseFen' => $baseFen])
                    ]);
                }

                /**
                 * Save form
                 * New base fen
                 */
                if (!empty($newMovesPlayed)) {
                    $saveForm = $this->createForm(
                        BuildMovesType::class,
                        ['moves' => $movesPlayed],
                        ['action' => $this->generateUrl('app_move_save_moves', ['course' => $course->getId(), 'baseFen' => $baseFen])]
                    );
                }

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
                    } else {
                        if ($myTurn) {
                            if (!isset($movePopularitiesMastersByLan)) {
                                $mlService->preloadMoves($fen, true);
                            }
                        } else {
                            if (!isset($movesPopularitiesByLan)) {
                                $mlService->preloadMoves($fen, false);
                            }
                        }
                    }
                } else {
                    $message = new PreloadMoves($course->getId(), $baseFen, implode(' ', array_map(function ($move) {
                        return $move->getLan();
                    }, $movesPlayed)));
                    $bus->dispatch($message);
                }

                return $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'nb_games' => $nbGames,
                    'nb_masters_games' => $nbMastersGames ?? null,
                    'expected_percentage' => $expectedPercentage,
                    'moves_forms' => $movesForms,
                    'form' => $this->createForm(BuildMovesType::class, ['moves' => [...$movesPlayed, new Move()]], [
                        'action' => $this->generateUrl('app_position_build_moves', ['course' => $course->getId(), 'baseFen' => $baseFen])
                    ]),
                    'previous_form' => $previousForm ?? null,
                    'save_form' => $saveForm ?? null,
                ]);
            }
        }

        return $this->render('position/index.html.twig', [
            'controller_name' => 'PositionController',
        ]);
    }
}
