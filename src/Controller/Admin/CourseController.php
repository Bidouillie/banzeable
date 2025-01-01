<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Form\CourseDeletionType;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/course')]
class CourseController extends AbstractController
{
    #[Route('/index', name: 'app_admin_course')]
    public function index(CourseRepository $repo): Response
    {
        $courses = $repo->findAll();

        return $this->render('admin/course/index.html.twig', [
            'courses' => $courses,
        ]);
    }

    #[Route('/new', name: 'app_admin_course_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/edit', name: 'app_admin_course_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(?Course $course, Request $request, EntityManagerInterface $em): Response
    {
        $course ??= new Course();
        $form = $this->createForm(CourseType::class, $course);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();
            return $this->redirectToRoute('app_admin_course');
        }

        if ($course->getId() !== null) {
            $deleteForm = $this->createForm(CourseDeletionType::class, $course, [
                'action' => $this->generateUrl('app_admin_course_delete', ['id' => $course->getId()]),
            ]);
        }

        return $this->render('admin/course/new.html.twig', [
            'controller_name' => 'CourseController',
            'form' => $form,
            'delete_form' => $deleteForm ?? null,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_course_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(?Course $course, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseDeletionType::class, $course);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($course);
            $em->flush();
            return $this->redirectToRoute('app_admin_course');
        }
    }
}
