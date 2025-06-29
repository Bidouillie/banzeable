<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildLanMovesType;
use App\Service\CandidatePaginatorService;
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
    public function buildMoves(?Course $course, ?string $startingFen, Request $request, FormFactoryInterface $factory, CandidatePaginatorService $cpService, MoveBuilderService $mbService): Response
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

                $candidateMoveStats = $mbService->buildCandidateMoves($course, $fen, $myTurn, $expectedPercentage, $popularityLoaded, $popularityMastersLoaded, $nbMovesSaved, $evalMissingFens);

                $nbMovesToShow = 4;
                if ($myTurn) {
                    $evalsNeeded = $cpService->filterMyMoves($candidateMoveStats, $course, $nbMovesToShow, $nbMovesSaved > 0, $popularityLoaded, $popularityMastersLoaded, count($evalMissingFens) <= 0);
                } else {
                    if (isset($expectedPercentage)) {
                        if ($popularityLoaded) {
                            $cpService->filterOppMoves($course, $candidateMoveStats, $expectedPercentage);
                        } else {
                            if (isset($fenMovesToLoad)) {
                                $fenMovesToLoad[] = $fen;
                            } else {
                                $fenMovesToLoad = [$fen];
                            }
                        }
                    }
                }

                if (empty($fenMovesToLoad)) {

                    /**
                     * Preload moves
                     */
                    if ($myTurn) {

                        if (!$popularityMastersLoaded || !$popularityLoaded) {
                            $fenMovesToLoad = [$fen];
                            $loadType = $popularityMastersLoaded ? 'optional' : 'important';
                        } else {
                            $movesToPreload = array_filter($candidateMoveStats, function ($moveStat) {
                                return $moveStat->show;
                            });
                        }

                        // Evals
                        if (count($evalMissingFens) > 0 && $popularityMastersLoaded) {

                            if ($nbMovesSaved <= 0 && !empty($evalsNeeded)) {
                                $evalFensToLoad = $evalMissingFens;
                            } else {
                                $evalFensToLoad = array_filter($evalMissingFens, function ($fenTo) use ($fen, $candidateMoveStats) {
                                    return $fenTo === $fen || $candidateMoveStats[$fenTo]->show;
                                });
                            }
                        }
                    } else {

                        $movesToPreload = array_filter($candidateMoveStats, function ($moveStat) {
                            return $moveStat->show;
                        });

                        // Evals
                        if (count($evalMissingFens) > 0 && $popularityLoaded) {

                            $evalFensToLoad = array_filter($evalMissingFens, function ($fenTo) use ($fen, $candidateMoveStats) {
                                return $fenTo === $fen || $candidateMoveStats[$fenTo]->show;
                            });
                        }
                    }
                } else {
                    $loadType = 'required';
                }

                $response = $this->render('position/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                    'candidate_move_stats' => $candidateMoveStats,
                    'can_save' => !!$diverged,
                    'moves_whole' => empty($fenMovesToLoad) && empty($evalFensToLoad),
                    'missing_percentages' => $expectedPercentages ?? [],
                    'diverge_index' => $divergeIndex ?? null,
                    'merge_index' => $mergeIndex ?? null,
                ]);

                $response->headers->set('X-Data-Fen', $fen);
                $response->headers->set('X-Data-My-Turn', json_encode($myTurn));

                if (!empty($fenMovesToLoad)) {
                    $response->headers->set('X-Data-Load-Moves', implode(',', $fenMovesToLoad));
                    $response->headers->set('X-Data-Load-Moves-Type', $loadType ?? '');
                }

                if (!empty($movesToPreload)) {
                    $response->headers->set('X-Data-Preload-Moves', implode(',', array_keys($movesToPreload)));
                }

                if (!empty($evalFensToLoad)) {
                    $response->headers->set('X-Data-Load-Evals', implode(',', $evalFensToLoad));
                }

                return $response;
            }
        }

        return $this->render('position/index.html.twig', [
            'controller_name' => 'PositionController',
        ]);
    }
}
