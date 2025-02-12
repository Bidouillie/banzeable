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
use Doctrine\ORM\EntityManagerInterface;
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
    public function buildMoves(?Course $course, ?string $baseFen, Request $request, MovePopularityRepository $mpRepo, MovePopularityMasterRepository $mpMasterRepo, EntityManagerInterface $em, MoveBuilderService $mbService, MoveLoaderService $mlService): Response
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

                $basePosition = $mbService->populateMoves($course, $baseFen, $movesPlayed, $newMovesPlayed);

                $em->clear();

                $expectedPercentage = $basePosition->getExpectedPercentage();

                $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                $movesPopularities = $mpRepo->findGroupedByLan($fen, $nbGames) ?? $mlService->loadMoves($fen, $nbGames);

                $movePopularitiesMasterByLan = $mpMasterRepo->findGroupedByLan($fen, $nbMastersGames) ?? [];

                $movesToPlay = $mbService->buildMoves($course, $fen, $movesPopularities, $movePopularitiesMasterByLan, $movesSavedByFen);
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
