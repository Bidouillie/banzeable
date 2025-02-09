<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\BuildMovesType;
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
    public function saveMoves(?Course $course, ?string $baseFen, Request $request, EntityManagerInterface $em, MoveBuilderService $mbService): Response
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

                $mbService->populateMoves($course, $baseFen, $moves);

                $em->flush();

                return $this->render('move/save_moves.html.twig', [
                    'course' => $course,
                ]);
            }
        }
    }
}
