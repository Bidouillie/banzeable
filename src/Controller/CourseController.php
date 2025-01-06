<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Entity\Variation;
use App\Form\BuildMoveType;
use App\Form\StudyToggleType;
use App\Message\LoadMastersMoves;
use App\Repository\CourseRepository;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use Chess\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\UX\Turbo\TurboBundle;

class CourseController extends AbstractController
{
    #[Route('/course/index', name: 'app_course')]
    public function index(CourseRepository $repo): Response
    {
        $courses = $repo->findAll();

        return $this->render('course/index.html.twig', [
            'courses' => $courses,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/course/{id}', name: 'app_course_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, ?Course $course, EntityManagerInterface $em): Response
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        $form = $this->createForm(StudyToggleType::class, [
            'action' => $course ? ($user->getStudies()->contains($course) ? 'remove' : 'add') : '',
        ]);

        $form->handleRequest($request);

        if ($course && $form->isSubmitted() && $form->isValid()) {

            if ($form->get('action')->getData() === 'add') {
                $user->addStudy($course);
            } else {
                $user->removeStudy($course);
            }

            $em->flush();

            // redirect to same route to update form
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/my_courses', name: 'app_my_courses')]
    public function my_courses(Security $security): Response
    {
        /**
         * @var User $user
         */
        $user = $security->getUser();

        $courses = $user->getCourses();

        return $this->render('course/my_courses.html.twig', [
            'courses' => $courses,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/course/{id}/build', name: 'app_course_build', requirements: ['id' => '\d+'])]
    public function build(#[MapEntity(id: 'id')] ?Course $course, SerializerInterface $serializer): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        $form = $this->createForm(BuildMoveType::class, null, [
            'action' => $this->generateUrl('app_course_build_moves', ['id' => $course->getId(), 'fen' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -']),
        ]);

        return $this->render('course/build.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/course/{id}/build-moves/{fen}', name: 'app_course_build_moves', requirements: ['id' => '\d+', 'fen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    public function buildMoves(#[MapEntity(id: 'id')] ?Course $course, ?string $fen, Request $request, MovePopularityRepository $repo, MovePopularityMasterRepository $masterRepo, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $mastersMoves = $masterRepo->findByFen($fen);
            $moves = $repo->findByFEN($fen);

            $mastersMoves = array_reduce($mastersMoves, function ($carry, $move) use ($fen, $bus) {
                if ($move->getSan() !== '-') {
                    if (!$move->isNextMovesLoaded()) {
                        $board = FenToBoardFactory::create($fen);
                        $board->play($board->turn, $move->getSan());
                        $bus->dispatch(new LoadMastersMoves($board->toFen()));
                        $move->setNextMovesLoaded(true);
                    }
                }

                $carry[$move->getSan()] = $move;
                return $carry;
            }, []);

            $em->flush();

            $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

            if ($myTurn) {
                usort($moves, function ($move1, $move2) use ($mastersMoves) {
                    $mastersMove1 = isset($mastersMoves[$move1->getSan()]) ? $mastersMoves[$move1->getSan()] : null;
                    $mastersMove2 = isset($mastersMoves[$move2->getSan()]) ? $mastersMoves[$move2->getSan()] : null;
                    $mastersGames1 = $mastersMove1 ? $mastersMove1->getWhite() + $mastersMove1->getBlack() + $mastersMove1->getDraws() : 0;
                    $mastersGames2 = $mastersMove2 ? $mastersMove2->getWhite() + $mastersMove2->getBlack() + $mastersMove2->getDraws() : 0;
                    return $mastersGames2 - $mastersGames1;
                });
            } else {
                usort($moves, function ($move1, $move2) {
                    return $move2->getWhite() - $move1->getWhite() + $move2->getBlack() - $move1->getBlack() + $move2->getDraws() - $move1->getDraws();
                });
            }

            $movesForms = [];

            foreach ($moves as $move) {

                $mastersMove = isset($mastersMoves[$move->getSan()]) ? $mastersMoves[$move->getSan()] : null;

                if ($move->getSan() !== '-') {
                    $board = FenToBoardFactory::create($fen);
                    $board->play($board->turn, $move->getSan());

                    $form = $this->createForm(BuildMoveType::class, null, [
                        'action' => $this->generateUrl('app_course_build_moves', ['id' => $course->getId(), 'fen' => $board->toFen()]),
                    ]);

                    $movesForms[] = [
                        'move' => $move,
                        'masters_games' => $mastersMove ? $mastersMove->getWhite() + $mastersMove->getBlack() + $mastersMove->getDraws() : 0,
                        'form' => $form->createView(),
                    ];
                } else {
                    $games = $move->getWhite() + $move->getBlack() + $move->getDraws();
                    $mastersGames = $mastersMove ? $mastersMove->getWhite() + $mastersMove->getBlack() + $mastersMove->getDraws() : 0;
                }
            }

            return $this->render('course/build_moves.html.twig', [
                'course' => $course,
                'fen' => $fen,
                'my_turn' => $myTurn,
                'games' => $games ?? 0,
                'masters_games' => $mastersGames ?? 0,
                'moves_forms' => $movesForms,
            ]);
        }
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/studies', name: 'app_studies')]
    public function studies(Security $security): Response
    {
        /**
         * @var User $user
         */
        $user = $security->getUser();

        $studies = $user->getStudies();

        return $this->render('course/studies.html.twig', [
            'studies' => $studies,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/study/{id}', name: 'app_study', requirements: ['id' => '\d+'])]
    #[Route('/study/{id}/{id_variation}', name: 'app_study_variation', requirements: ['id' => '\d+', 'id_variation' => '\d+'])]
    public function study(#[MapEntity(id: 'id')] ?Course $course, #[MapEntity(id: 'id_variation')] ?Variation $variation, SerializerInterface $serializer): Response
    {
        $this->denyAccessUnlessGranted('course.studies', $course);

        $variation = $variation ?? $course->getVariations()->first();

        return $this->render('course/study.html.twig', [
            'course' => $course,
            'selected_variation' => $variation,
            'variation_encoded' => $serializer->serialize($variation, 'json', ['groups' => ['move', 'notation']]),
        ]);
    }
}
