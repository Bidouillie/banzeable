<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Entity\Position;
use App\Form\BuildMovesType;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use App\Service\MoveLoaderService;
use Chess\FenToBoardFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
    #[Route('/load-moves/{course}/{baseFen}', name: 'app_position_build_moves', requirements: ['course' => '\d+', 'baseFen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function buildMoves(?Course $course, ?string $baseFen, Request $request, MovePopularityRepository $mpRepo, MovePopularityMasterRepository $mpMasterRepo, MoveBuilderService $mbService, MoveLoaderService $mlService): Response
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
                    }

                    $move->setFenTo($fenTo);
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

                $movePopularitiesByFenLan = $mpRepo->findGroupedByFenLan($fens);

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
                            $mbService->updateExpectedPercentageRecursiveFront($positionReached, $positionsSavedByFen);
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

                $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                if (isset($movePopularitiesByFenLan[$fen])) {
                    $movesPopularities = $movePopularitiesByFenLan[$fen]['moves'];
                    $nbGames = $movePopularitiesByFenLan[$fen]['nbGames'];
                } else {
                    $movesPopularities = $mlService->loadMoves($fen, $nbGames);
                }

                $movePopularitiesMasterByLan = $mpMasterRepo->findGroupedByLan($fen, $nbMastersGames);

                $movesToPlay = $mbService->buildMoves($course, $fen, $movesPopularities, $movePopularitiesMasterByLan);
                $movesForms = [];
                $fens = [];
                foreach ($movesToPlay as $move) {
                    $board = FenToBoardFactory::create($fen);
                    $board->playLan($board->turn, $move->getLan());
                    $fens[] = $board->toFen();
                    $movesForms[] = [
                        'move' => $move,
                        'coverage' => 0,
                        'saved' => isset($movesSavedByFen[$fen][$board->toFen()]),
                        'next_saved' => isset($positionsSavedByFen[$board->toFen()]),
                    ];
                }

                $movesPopularitiesByFenLan = array_reduce($mpRepo->findByFenGrouped($fens), function ($carry, $fen) {
                    $carry[$fen] = $fen;
                    return $carry;
                }, []);
                $movesPopularitiesMasterByFenGrouped = array_reduce($mpMasterRepo->findByFenGrouped($fens), function ($carry, $fen) {
                    $carry[$fen] = $fen;
                    return $carry;
                }, []);

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                $mlService->preloadMoves(array_map(function ($move) {
                    return $move->getFenTo();
                }, array_filter($movesToPlay, function ($move) use ($expectedPercentage, $nbGames, $nbMastersGames, $course, $myTurn) {
                    return $myTurn ? ($move->getPopularityMaster() === null ? false : $move->getPopularityMaster()->getTotal() / $nbMastersGames > 1 / 100) : $expectedPercentage * $move->getPopularity()->getTotal() / $nbGames > 1 / $course->getCoverage();
                })), $movesPopularitiesByFenLan, $movesPopularitiesMasterByFenGrouped);

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

                return $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'nb_games' => $nbGames,
                    'nb_masters_games' => $nbMastersGames,
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
