<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Form\CourseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/course')]
class CourseController extends AbstractController
{
    #[Route('/index', name: 'app_admin_course')]
    public function index(): Response
    {
        return $this->render('admin/course/index.html.twig', [
            'controller_name' => 'CourseController',
        ]);
    }

    #[Route('/new', name: 'app_admin_course_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();
        }

        return $this->render('admin/course/new.html.twig', [
            'controller_name' => 'CourseController',
            'form' => $form,
        ]);
    }
}
