<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildMovesType;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use App\Service\MoveLoaderService;
use Chess\FenToBoardFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

                $expectedPercentage = $mbService->populateMoves($course, $baseFen, $movesPlayed, $newMovesPlayed, $movePopularitiesByFenLan);

                $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                if (isset($movePopularitiesByFenLan[$fen])) {
                    $movesPopularities = $movePopularitiesByFenLan[$fen]['moves'];
                    $nbGames = $movePopularitiesByFenLan[$fen]['nbGames'];
                } else {
                    $movesPopularities = $mpRepo->findGroupedByLan($fen, $nbGames) ?? $mlService->loadMoves($fen, $nbGames);
                }

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                $movePopularitiesMasterByLan = $myTurn ? ($mpMasterRepo->findGroupedByLan($fen, $nbMastersGames) ?? []) : [];

                $movesToPlay = $mbService->buildMoves($course, $fen, $movesSavedByFen, $movesPopularities, $movePopularitiesMasterByLan);
                $movesForms = [];
                $fens = [];
                foreach ($movesToPlay as $move) {
                    $board = FenToBoardFactory::create($fen);
                    $board->playLan($board->turn, $move->getLan());
                    $fens[] = $board->toFen();
                    $movesForms[] = [
                        'move' => $move,
                        'saved' => isset($movesSavedByFen[$fen][$board->toFen()]),
                        'next_saved' => isset($positionsSavedByFen[$board->toFen()]),
                    ];
                }

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

                return $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'nb_games' => $nbGames,
                    'nb_masters_games' => $nbMastersGames ?? 0,
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
