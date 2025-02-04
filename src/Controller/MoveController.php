<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Entity\Position;
use App\Form\BuildMovesType;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use Chess\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/move')]
class MoveController extends AbstractController
{
    #[Route('/index', name: 'app_move')]
    public function index(): Response
    {
        return $this->render('move/index.html.twig', [
            'controller_name' => 'MoveController',
        ]);
    }

    #[Route('/save-moves/{course}/{baseFen}', name: 'app_move_save_moves', requirements: ['course' => '\d+', 'baseFen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function saveMoves(?Course $course, ?string $baseFen, Request $request, MovePopularityRepository $mpRepo, MovePopularityMasterRepository $mpMasterRepo, EntityManagerInterface $em, MoveBuilderService $mbService): Response
    {
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

                        $em->persist($position);
                    }

                    $move->setFenTo($fenTo);

                    $em->persist($move);
                }

                $fens = array_unique(array_merge(array_reduce($movesPlayed, function ($carry, $move) {
                    $carry[] = $move->getFenFrom();
                    $carry[] = $move->getFenTo();
                    return $carry;
                }, []), [$baseFen]));

                $movePopularitiesByFenLan = $mpRepo->findGroupedByFenLan($fens);

                if (!isset($basePosition)) {
                    $basePosition = empty($movesPlayed) ? $positions[$baseFen] : $positions[end($movesPlayed)->getFenTo()];
                }
                $newMovesPlayed = isset($keyBase) ? array_slice($movesPlayed, $keyBase) : [];

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

                $em->flush();

                return $this->render('move/save_moves.html.twig', [
                    'course' => $course,
                ]);
            }
        }
    }
}
