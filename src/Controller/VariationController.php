<?php

namespace App\Controller;

use App\Form\MoveBuilderVariationType;
use App\Repository\NotationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/variation')]
class VariationController extends AbstractController
{
    #[Route('/index', name: 'app_variation')]
    public function index(): Response
    {
        return $this->render('variation/index.html.twig', [
            'controller_name' => 'VariationController',
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/new', name: 'app_variation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, NotationRepository $repo): Response
    {
        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $this->createForm(MoveBuilderVariationType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $newVariation = $form->get('variation')->getData();
                $selectedPercentHistory = $form->get('selectedPercentHistory')->getData();
                $movesMerged = $form->get('movesMerged')->getData();

                $course = $newVariation->getCourse();

                $this->denyAccessUnlessGranted('course.owns', $course);

                $moves = $newVariation->getMoves();

                $FENsReachedToMerge = array_map(function ($move) {
                    return $move->getFENReached();
                }, $movesMerged->getValues());

                $variationsMultipliersToMerge = [];
                foreach ($FENsReachedToMerge as $FENReachedToMerge) {
                    $variationsMultipliersToMerge[$FENReachedToMerge] = ['multiplier' => null, 'variations' => []];
                }

                /**
                 * Remove last move if not played by playing side
                 */
                if (count($moves) % 2 === ($course->isBlackOrientation() ? 1 : 0)) {
                    $newVariation->removeMove($moves->last());
                }

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
                $totalSelectedMultiplier = 1;
                foreach ($newVariation->getMoves() as $key => $move) {

                    $selectedMultiplier = $selectedPercentHistory[$key];
                    $totalSelectedMultiplier *= $selectedMultiplier;
                    $move->setSelectedMultiplier($selectedMultiplier);
                    $move->setTotalSelectedMultiplier($totalSelectedMultiplier);

                    if (in_array($move->getFENReached(), $FENsReachedToMerge)) {
                        $variationsMultipliersToMerge[$move->getFENReached()]['multiplier'] = $totalSelectedMultiplier;
                    }

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
                 * Add them to variations to merge
                 */
                $orderedMoves = [];
                foreach ($course->getVariations() as $variation) {
                    $bufferOrderedMoves = &$orderedMoves;
                    foreach ($variation->getMoves() as $move) {
                        if (in_array($move->getFENReached(), $FENsReachedToMerge)) {
                            $variationsMultipliersToMerge[$move->getFENReached()]['variations'][] = $variation;
                        }
                        $notation = $move->getNotation();
                        $SAN = $notation->getText();
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

                /**
                 * Update multiplier of merged variations
                 */
                foreach ($variationsMultipliersToMerge as $FENReachedToMerge => $variationsMultiplierToMerge) {
                    if (isset($variationsMultiplierToMerge['multiplier'])) {
                        foreach ($variationsMultiplierToMerge['variations'] as $variationToMerge) {
                            $multiplier = 1;
                            foreach ($variationToMerge->getMoves() as $move) {
                                if ($move->getFENReached() === $FENReachedToMerge) {
                                    $multiplier = $variationsMultiplierToMerge['multiplier'] / $move->getTotalSelectedMultiplier();
                                    $move->setSelectedMultiplier($move->getSelectedMultiplier() * $multiplier);
                                }
                                $move->setTotalSelectedMultiplier($move->getTotalSelectedMultiplier() * $multiplier);
                            }
                        }
                    }
                }

                $em->flush();
            }

            return $this->render('variation/new.html.twig', [
                'course' => $course,
            ]);
        }
    }
}
