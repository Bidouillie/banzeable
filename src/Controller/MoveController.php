<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\BuildMovesType;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function saveMoves(?Course $course, ?string $baseFen, Request $request, MovePopularityRepository $mpRepo, EntityManagerInterface $em, MoveBuilderService $mbService): Response
    {
        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $this->createForm(BuildMovesType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                /**
                 * @var array<Move> $movesPlayed
                 */
                $moves = $form->get('moves')->getData();

                $movesSavedByFen = $course->getRepertoireMovesByFen();
                $positionsSavedByFen = $course->getPositionsByFen();

                $mbService->populateMoves($course, $baseFen, $moves, $newMoves);

                /**
                 * Completion
                 */
                $movesByFen = $movesSavedByFen;
                foreach ($newMoves as $move) {
                    if (!isset($movesByFen[$move->getFenFrom()][$move->getFenTo()])) {
                        $movesByFen[$move->getFenFrom()][$move->getFenTo()] = $move;
                    }
                }

                $positionsByFenLan = $positionsSavedByFen;
                foreach ($movesByFen as $fenFrom => $movesByFenTo) {
                    foreach ($movesByFenTo as $fenTo => $move) {
                        if (!isset($positionsByFenLan[$fenFrom])) {
                            $positionsByFenLan[$fenFrom] = [
                                'position' => $move->getPositionFrom(),
                                'previousMoves' => [],
                                'nextMoves' => [],
                            ];
                        }
                        if (!isset($positionsByFenLan[$fenTo])) {
                            $positionsByFenLan[$fenTo] = [
                                'position' => $move->getPositionTo(),
                                'previousMoves' => [],
                                'nextMoves' => [],
                            ];
                        }
                        if (isset($positionsByFenLan[$fenFrom]) && isset($positionsByFenLan[$fenTo])) {
                            $positionsByFenLan[$fenFrom]['nextMoves'][$move->getLan()] = $move;
                            $positionsByFenLan[$fenTo]['previousMoves'][$move->getLan()] = $move;
                        }
                    }
                }

                $fens = [];
                foreach ($positionsByFenLan as $position) {
                    $fens[$position['position']->getFen()] = $position['position']->getFen();
                }
                $movePopularitiesByFenLan = $mpRepo->findGroupedByFenLan($fens);

                $mbService->updateCompletion($course, $positionsByFenLan, $movePopularitiesByFenLan, $positionsByFenLan[$baseFen]);

                $em->flush();

                return $this->render('move/save_moves.html.twig', [
                    'course' => $course,
                ]);
            }
        }
    }
}
