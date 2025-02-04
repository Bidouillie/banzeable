<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
}
