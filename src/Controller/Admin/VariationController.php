<?php

namespace App\Controller\Admin;

use App\Entity\Variation;
use App\Form\VariationFromPGNType;
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
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $variation = new Variation();
        $form = $this->createForm(VariationFromPGNType::class, $variation);

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($variation);
            $em->flush();
        }

        return $this->render('admin/variation/new.html.twig', [
            'controller_name' => 'VariationController',
            'form' => $form,
        ]);
    }
}
