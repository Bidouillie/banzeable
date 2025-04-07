<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildLanMovesType;
use App\Service\MoveBuilderService;
use Chess\FenToBoardFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
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
    #[Route('/build-moves/{course}/{startingFen}', name: 'app_position_build_moves', requirements: ['course' => '\d+', 'startingFen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function buildMoves(?Course $course, ?string $startingFen, Request $request, FormFactoryInterface $factory, MoveBuilderService $mbService): Response
    {
        // TODO Check if the course is a repertoire
        $this->denyAccessUnlessGranted('course.owns', $course);

        // sleep(1);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $factory->createNamed('build_moves_form', BuildLanMovesType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $fen = strval($form->get('fen')->getData());

                /**
                 * @var array<Move> $movesPlayed
                 */
                $movesPlayed = $form->get('lanMoves')->getData();

                $expectedPercentage = floatval($form->get('expectedPercentage')->getData() ?? 1);

                $diverged = boolval($form->get('diverged')->getData());

                // TODO check if form data is consistent

                if (count($movesPlayed) > 0) {

                    if (count($movesPlayed) > 10) {
                        throw new HttpException(425, "Too many requests");
                    }

                    $merged = boolval($form->get('merged')->getData());

                    $startingTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create(reset($movesPlayed)->getFenFrom())->turn;

                    $expectedPercentages = $mbService->getExpectedPercentage($course, $startingTurn, $movesPlayed, $expectedPercentage, $diverged, $merged, $fenMovesToLoad, $divergeIndex, $mergeIndex);

                    /**
                     * @var null|float $expectedPercentage
                     */
                    $expectedPercentage = empty($expectedPercentages) ? null : end($expectedPercentages);
                }

                $fen = empty($movesPlayed) ? $fen ?? $startingFen : end($movesPlayed)->getFenTo();

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

                $movesToPlay = $mbService->buildCandidateMoves($course, $fen, $myTurn, $expectedPercentage, $popularityLoaded, $popularityMastersLoaded, $nbMovesSaved, $evalMissingFens);

                /**
                 * Filtering and sorting
                 */
                $fensToShow = [];
                if ($myTurn) {
                    if ($nbMovesSaved > 0) {
                        foreach ($movesToPlay as $key => $movestat) {
                            if ($movestat['saved']) {
                                $movesToPlay[$key]['show'] = true;
                                $fensToShow[] = $movestat['move']->getFenTo();
                            }
                        }
                    } elseif ($popularityMastersLoaded) {
                        foreach ($movesToPlay as $key => $movestat) {
                            if ($movestat['selected_percentage_masters'] > 1 / 100) {
                                $movesToPlay[$key]['show'] = true;
                                $fensToShow[] = $movestat['move']->getFenTo();
                            }
                        }
                    }
                } elseif (isset($expectedPercentage)) {
                    if (!$popularityLoaded) {
                        if (isset($fenMovesToLoad)) {
                            $fenMovesToLoad[] = $fen;
                        } else {
                            $fenMovesToLoad = [$fen];
                        }
                    } elseif ($expectedPercentage > 0) {
                        $threshold = $course->getTrueCoverage() / $expectedPercentage;

                        foreach ($movesToPlay as $key => $movestat) {
                            if ($movestat['selected_percentage'] > $threshold) {
                                $movesToPlay[$key]['show'] = true;
                                $fensToShow[] = $movestat['move']->getFenTo();
                            }
                        }
                    }
                }

                // Sort by eval
                if ((!$myTurn || $nbMovesSaved <= 0) && count($evalMissingFens) <= 0 && count($fensToShow) < 3) {
                    $isBlack = $course->isBlackOrientation();
                    $ca = $isBlack ? -1 : 1;
                    $cb = $isBlack ? 1 : -1;
                    usort($movesToPlay, function ($a, $b) use ($ca, $cb) {
                        if (isset($a['show']) || isset($b['show'])) {
                            return isset($a['show']) ? -1 : 1;
                        }
                        if (!isset($a['eval']) || !isset($b['eval'])) {
                            return isset($a['eval']) ? -1 : 1;
                        }
                        if ($a['mate'] xor $b['mate']) {
                            return ($a['mate'] ? $a['eval'] < 0 : $b['eval'] > 0) ? $ca : $cb;
                        }
                        return $a['eval'] < $b['eval'] ? $ca : $cb;
                    });

                    foreach ($movesToPlay as $key => $movestat) {
                        if (empty($movestat['show'])) {
                            $movesToPlay[$key]['show'] = true;
                            $fensToShow[] = $movestat['move']->getFenTo();
                            if (count($fensToShow) >= 3) {
                                break;
                            }
                        }
                    }
                }

                $response = $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'moves_forms' => $movesToPlay,
                    'can_save' => !!$diverged,
                    'moves_whole' => !isset($message),
                    'missing_percentages' => $expectedPercentages ?? [],
                    'diverge_index' => $divergeIndex ?? null,
                    'merge_index' => $mergeIndex ?? null,
                ]);

                /**
                 * Preload moves
                 */
                $response->headers->set('X-Data-Fen', $fen);
                $response->headers->set('X-Data-My-Turn', json_encode($myTurn));

                if (!empty($fenMovesToLoad)) {
                    $response->headers->set('X-Data-Load-Moves', implode(',', $fenMovesToLoad));
                    $response->headers->set('X-Data-Load-Moves-Type', 'required');
                } else {
                    if ($myTurn && (!$popularityMastersLoaded || !$popularityLoaded)) {
                        $response->headers->set('X-Data-Load-Moves', $fen);
                        $response->headers->set('X-Data-Load-Moves-Type', !$popularityMastersLoaded ? 'important' : 'optional');
                    } elseif (count($fensToShow) > 0) {
                        $response->headers->set('X-Data-Preload-Moves', implode(',', $fensToShow));
                    }

                    // Evals
                    if (count($evalMissingFens) > 0 && (($myTurn && $popularityMastersLoaded) || (!$myTurn && $popularityLoaded))) {

                        if (($myTurn && $nbMovesSaved > 0) || !$myTurn) {
                            $evalFensToLoad = array_filter($fensToShow, function ($evalMissingFen) use ($evalMissingFens) {
                                return isset($evalMissingFens[$evalMissingFen]);
                            });
                        } elseif ($myTurn && $nbMovesSaved <= 0 && count($fensToShow) < 3) {
                            $evalFensToLoad = array_keys($evalMissingFens);
                        }

                        if (!empty($evalFensToLoad)) {
                            $response->headers->set('X-Data-Load-Evals', implode(',', $evalFensToLoad));
                        }
                    }
                }

                return $response;
            }
        }

        return $this->render('position/index.html.twig', [
            'controller_name' => 'PositionController',
        ]);
    }
}
