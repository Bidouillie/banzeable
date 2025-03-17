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
use App\Repository\PositionRepository;
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
    #[Route('/build-moves/{course}/{baseFen}', name: 'app_position_build_moves', requirements: ['course' => '\d+', 'baseFen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function buildMoves(?Course $course, ?string $baseFen, Request $request, FormFactoryInterface $factory, MoveRepository $moveRepo, PositionRepository $positionRepo, MoveBuilderService $mbService, MessageBusInterface $bus): Response
    {
        // TODO Check if the course is a repertoire
        $this->denyAccessUnlessGranted('course.owns', $course);

        // sleep(1);

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

                // TODO check if form data is consistent

                if ($missingPercentages > 0) {

                    if ($missingPercentages > 5) {
                        throw new HttpException(425, "Too many requests");
                    }

                    $moves = array_slice($movesPlayed, -$missingPercentages);

                    $startingTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create(reset($moves)->getFenFrom())->turn;

                    $expectedPercentages = $mbService->getExpectedPercentage($course, $startingTurn, $moves, $expectedPercentage, $fensToLoad);

                    /**
                     * @var null|float $expectedPercentage
                     */
                    $expectedPercentage = empty($expectedPercentages) ? null : end($expectedPercentages);

                    var_dump($expectedPercentages);
                }

                $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

                if (isset($expectedPercentage)) {
                    $movesSavedByLan = $moveRepo->findGroupedByLan($course, $fen);

                    if (count($movesSavedByLan) > 0) {
                        $positionsReached = $positionRepo->findGroupedByFen($course, array_map(function ($move) {
                            return $move->getFenTo();
                        }, $movesSavedByLan));
                    }
                }

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                $movesToPlay = $mbService->buildCandidateMoves($fen, $myTurn, $expectedPercentage, $movesSavedByLan ?? null, $positionsReached ?? null, $myTurn, $saved);

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
