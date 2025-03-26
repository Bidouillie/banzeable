<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildLanMovesType;
use App\Message\LoadMovesOptional;
use App\Message\LoadMovesRequired;
use App\Message\PreloadMyMoves;
use App\Message\PreloadOppMoves;
use App\Repository\MoveRepository;
use App\Service\MoveBuilderService;
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
    #[Route('/build-moves/{course}/{startingFen}', name: 'app_position_build_moves', requirements: ['course' => '\d+', 'startingFen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function buildMoves(?Course $course, ?string $startingFen, Request $request, FormFactoryInterface $factory, MoveRepository $moveRepo, MoveBuilderService $mbService, MessageBusInterface $bus): Response
    {
        // TODO Check if the course is a repertoire
        $this->denyAccessUnlessGranted('course.owns', $course);

        // sleep(1);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $factory->createNamed('build_moves_form', BuildLanMovesType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $fen = $form->get('fen')->getData();

                /**
                 * @var array<Move> $movesPlayed
                 */
                $movesPlayed = $form->get('lanMoves')->getData();

                $diverged = $form->get('diverged')->getData();

                $merged = $form->get('merged')->getData();

                $expectedPercentage = floatval($form->get('expectedPercentage')->getData() ?? 1);

                // TODO check if form data is consistent

                if (count($movesPlayed) > 0) {

                    if (count($movesPlayed) > 10) {
                        throw new HttpException(425, "Too many requests");
                    }

                    if (!$diverged) {
                        $movesSavedByFenLan = $moveRepo->findSavedGroupedByFenLan($course, array_map(function ($move) {
                            return $move->getFenFrom();
                        }, $movesPlayed));
                    }

                    $startingTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create(reset($movesPlayed)->getFenFrom())->turn;

                    $expectedPercentages = $mbService->getExpectedPercentage($course, $startingTurn, $movesPlayed, $expectedPercentage, $fensToLoad, $movesSavedByFenLan ?? null, $divergeIndex, $mergeIndex);

                    /**
                     * @var null|float $expectedPercentage
                     */
                    $expectedPercentage = empty($expectedPercentages) ? null : end($expectedPercentages);
                }

                $fen = empty($movesPlayed) ? $fen ?? $startingFen : end($movesPlayed)->getFenTo();

                if (isset($expectedPercentage)) {
                    $movesSavedByLan = $moveRepo->findGroupedByLan($course, $fen);
                }

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                $movesToPlay = $mbService->buildCandidateMoves($course, $fen, $myTurn, $expectedPercentage, $movesSavedByLan ?? null, $myTurn, $saved);

                if ($myTurn) {
                    foreach ($movesToPlay as $key => $movestat) {
                        $movesToPlay[$key]['show'] = ($saved && $movestat['saved']) || (!$saved && isset($movestat['selected_masters']) && $movestat['selected_masters'] > 1 / 100);
                    }
                } else {
                    $threshold = isset($expectedPercentage) && $expectedPercentage > 0 ? $course->getTrueCoverage() / $expectedPercentage : null;

                    foreach ($movesToPlay as $key => $movestat) {
                        $movesToPlay[$key]['show'] = isset($threshold) && isset($movestat['selected']) && $movestat['selected'] > $threshold;
                    }
                }

                /**
                 * Preload moves
                 */
                $messages = [];
                if (isset($expectedPercentage)) {
                    $movestat = reset($movesToPlay);
                    if ($movestat !== 'false') {
                        $load = !isset($movestat['selected']);
                        if ($myTurn) {
                            $loadMasters = !isset($movestat['selected_masters']);
                            if ($load || $loadMasters) {
                                $messages[] = new LoadMovesOptional($fen, $load && $loadMasters ? null : $loadMasters);
                            }

                            if (!$loadMasters) {
                                $preload = true;
                            }
                        } elseif ($load) {
                            $messages[] = new LoadMovesRequired([$fen]);
                        } else {
                            $preload = true;
                        }

                        if (isset($preload)) {
                            $fensToPreload = array_reduce($movesToPlay, function ($carry, $movestat) {
                                if ($movestat['show']) {
                                    $carry[] = $movestat['move']->getFenTo();
                                }
                                return $carry;
                            }, []);

                            if (count($fensToPreload) > 0) {
                                $messages[] = $myTurn ? new PreloadOppMoves($fensToPreload) : new PreloadMyMoves($fensToPreload);
                            }
                        }
                    }
                } elseif (!empty($fensToLoad)) {
                    $messages[] = new LoadMovesRequired($fensToLoad);
                }

                foreach ($messages as $message) {
                    $bus->dispatch($message);
                }

                return $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'moves_forms' => $movesToPlay,
                    'can_save' => !!$diverged,
                    'moves_whole' => !isset($message),
                    'missing_percentages' => $expectedPercentages ?? [],
                    'diverge_index' => $divergeIndex ?? null,
                    'merge_index' => $mergeIndex ?? null,
                ]);
            }
        }

        return $this->render('position/index.html.twig', [
            'controller_name' => 'PositionController',
        ]);
    }
}
