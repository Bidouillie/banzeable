<?php

namespace App\Controller\Admin;

use App\Entity\Variation;
use App\Form\VariationFromPGNType;
use App\Repository\NotationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/variation')]
class VariationController extends AbstractController
{
    #[Route('/index', name: 'app_admin_variation')]
    public function index(): Response
    {
        return $this->render('admin/variation/index.html.twig', [
            'controller_name' => 'VariationController',
        ]);
    }

    #[Route('/new', name: 'app_admin_variation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, NotationRepository $repo): Response
    {
        $variation = new Variation();
        $form = $this->createForm(VariationFromPGNType::class, $variation);

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {

            /**
             * Get all notations met in variation and order them
             */
            $FENs = [];
            foreach($variation->getMoves() as $move) {
                $FENs[] = $move->getNotation()->getFEN();
            }

            $notations = $repo->findByFEN($FENs);

            $orderedNotations = [];
            foreach($notations as $notation) {
                if(!isset($orderedNotations[$notation->getFEN()])) {
                    $orderedNotations[$notation->getFEN()] = [];
                }
                
                $orderedNotations[$notation->getFEN()][$notation->getText()] = $notation;
            }
            
            /**
             * Linking the moves to the notations that are already created
             */
            foreach($variation->getMoves() as $move) {
                $notation = $move->getNotation();

                if(isset($orderedNotations[$notation->getFEN()]) && isset($orderedNotations[$notation->getFEN()][$notation->getText()])) {
                    $move->setNotation($orderedNotations[$notation->getFEN()][$notation->getText()]);
                } else {
                    if(!isset($orderedNotations[$notation->getFEN()])) {
                        $orderedNotations[$notation->getFEN()] = [];
                    }
                    $orderedNotations[$notation->getFEN()][$notation->getText()] = $move->getNotation();
                }
            }

            $em->persist($variation);
            $em->flush();
        }

        return $this->render('admin/variation/new.html.twig', [
            'controller_name' => 'VariationController',
            'form' => $form,
        ]);
    }
}
