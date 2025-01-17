<?php

namespace App\Controller;

use App\Entity\Variation;
use App\Form\VariationFromPGNMovesType;
use App\Repository\NotationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

class VariationController extends AbstractController
{
    #[Route('/variation', name: 'app_variation')]
    public function index(): Response
    {
        return $this->render('variation/index.html.twig', [
            'controller_name' => 'VariationController',
        ]);
    }

    #[Route('/new', name: 'app_variation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, NotationRepository $repo): Response
    {
        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $newVariation = new Variation();
            $form = $this->createForm(VariationFromPGNMovesType::class, $newVariation);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $course = $newVariation->getCourse();
                $newVariation->setBlackOrientation($course->isBlackOrientation());

                $newVariation->setName('repertoire');

                /**
                 * Get all notations met in variation and order them
                 */
                $FENs = [];
                foreach ($newVariation->getMoves() as $move) {
                    $FENs[] = $move->getNotation()->getFEN();
                }

                $notations = $repo->findByFEN($FENs);

                $orderedNotations = [];
                foreach ($notations as $notation) {
                    if (!isset($orderedNotations[$notation->getFEN()])) {
                        $orderedNotations[$notation->getFEN()] = [];
                    }

                    $orderedNotations[$notation->getFEN()][$notation->getText()] = $notation;
                }

                /**
                 * Linking the moves to the notations that are already created
                 * Add percentage history
                 */
                $selectedPercentHistory = $newVariation->getSelectedPercentHistory();
                $selectedMultiplier = 1;
                foreach ($newVariation->getMoves() as $key => $move) {

                    $selectedMultiplier *= floatval($selectedPercentHistory[$key]);
                    $move->setSelectedMultiplier($selectedMultiplier);

                    $notation = $move->getNotation();
                    if (isset($orderedNotations[$notation->getFEN()]) && isset($orderedNotations[$notation->getFEN()][$notation->getText()])) {
                        $move->setNotation($orderedNotations[$notation->getFEN()][$notation->getText()]);
                    } else {
                        if (!isset($orderedNotations[$notation->getFEN()])) {
                            $orderedNotations[$notation->getFEN()] = [];
                        }
                        $orderedNotations[$notation->getFEN()][$notation->getText()] = $notation;
                    }
                }

                /**
                 * Get saved variations and order their moves in a tree
                 */
                $orderedMoves = [];
                foreach ($course->getVariations() as $variation) {
                    $bufferOrderedMoves = &$orderedMoves;
                    foreach ($variation->getMoves() as $move) {
                        $notation = $move->getNotation();
                        $SAN = $move->getNotation()->getText();
                        if (!isset($bufferOrderedMoves[$SAN])) {
                            $bufferOrderedMoves[$SAN] = [];
                        }
                        $bufferOrderedMoves = &$bufferOrderedMoves[$SAN];
                    }
                    $bufferOrderedMoves['-'] = $variation;
                    unset($bufferOrderedMoves);
                }

                /**
                 * Get variation that follow the new variation the longest
                 */
                unset($variation);
                $movesExist = [];
                foreach ($newVariation->getMoves() as $move) {
                    $SAN = $move->getNotation()->getText();
                    if (isset($orderedMoves[$SAN])) {
                        $orderedMoves = $orderedMoves[$SAN];
                        $movesExist[] = $move;
                    } elseif (isset($orderedMoves['-'])) {
                        $variation = $orderedMoves['-'];
                    } else {
                        break;
                    }
                }

                /**
                 * Add moves to existing variation or save new variation
                 */
                if (isset($variation)) {
                    foreach ($newVariation->getMoves() as $move) {
                        if (!in_array($move, $movesExist)) {
                            $variation->addMove($move);
                        }
                    }
                } elseif (!isset($orderedMoves['-'])) {
                    $em->persist($newVariation);
                }

                $em->flush();
            }

            return $this->render('variation/new.html.twig', [
                'course' => $course,
            ]);
        }
    }
}
