<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Move;
use App\Form\BuildLanMovesType;
use App\Service\MoveBuilderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
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

    #[Route('/save-moves/{course}/{startingFen}', name: 'app_move_save_moves', requirements: ['course' => '\d+', 'startingFen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function saveMoves(?Course $course, ?string $startingFen, Request $request, FormFactoryInterface $factory, EntityManagerInterface $em, MoveBuilderService $mbService): Response
    {
        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $factory->createNamed('save_moves_form', BuildLanMovesType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $baseFen = strval($form->get('fen')->getData());

                /**
                 * @var array<Move> $movesPlayed
                 */
                $movesPlayed = $form->get('lanMoves')->getData();

                $movesPlayed = $mbService->populateMoves($course, $movesPlayed);

                $mergeKey = $mbService->setExpectedPercentage($movesPlayed);

                /**
                 * Persist new positions and new moves
                 */
                foreach ($movesPlayed as $move) {

                    $em->persist($move->getPositionTo());
                    $em->persist($move);
                }

                /**
                 * Completion
                 */
                $mbService->updateCompletion($baseFen, $course->getTrueCoverage());

                $em->flush();

                if (isset($mergeKey)) {
                    $newPercentages = array_map(function (Move $move) {
                        $expectedPercentage = $move->getPositionTo()->getExpectedPercentage();
                        return isset($expectedPercentage) ? number_format($expectedPercentage, 9) : null;
                    }, array_slice($movesPlayed, $mergeKey));
                }

                return $this->render('move/save_moves.html.twig', [
                    'course' => $course,
                    'new_percentages' => $newPercentages ?? [],
                ]);
            }
        }

        return $this->render('move/index.html.twig', [
            'controller_name' => 'MoveController',
        ]);
    }
}
