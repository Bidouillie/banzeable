<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Entity\Variation;
use App\Form\BuildMoveType;
use App\Form\StudyToggleType;
use App\Form\VariationFromPGNMovesType;
use App\Repository\CourseRepository;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use App\Repository\NotationRepository;
use App\Repository\VariationRepository;
use App\Service\MoveBuilderService;
use Chess\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -';

        $form = $this->createForm(
            BuildMoveType::class,
            [
                'ply' => 0,
                'selectedPercentHistory' => [],
                'canSave' => false,
            ],
            [
                'action' => $this->generateUrl('app_course_build_moves', ['id' => $course->getId(), 'fen' => $fen]),
            ]
        );

        return $this->render('course/build.html.twig', [
            'course' => $course,
            'course_encoded' => $serializer->serialize($course, 'json', ['groups' => ['Default']]),
            'form' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/course/{id}/build-moves/{fen}', name: 'app_course_build_moves', requirements: ['id' => '\d+', 'fen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    #[Route('/course/{id}/build-moves/{fen}/{fromLan}', name: 'app_course_build_moves_from_lan', requirements: ['id' => '\d+', 'fen' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$', 'lan' => '^([a-h][1-8]){2}$'])]
    public function buildMoves(#[MapEntity(id: 'id')] ?Course $course, ?string $fen, ?string $fromLan, Request $request, MovePopularityRepository $repo, MovePopularityMasterRepository $masterRepo, VariationRepository $variationRepo, NotationRepository $notationRepo, MoveBuilderService $mbService, FormFactoryInterface $formFactory): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $formFactory->createNamed('build_move' . (isset($fromLan) ? "_$fromLan" : ''), BuildMoveType::class);
            $form->handleRequest($request);

            // TODO check if form submitted ?
            $fromFEN = $form->get('fromFEN')->getData();
            $fromSAN = $form->get('fromSAN')->getData();
            $ply = intval($form->get('ply')->getData());
            $selectedPercentHistory = $form->get('selectedPercentHistory')->getData();
            $canSave = $form->get('canSave')->getData();

            $mMoves = $masterRepo->findByFen($fen, $mastersGames);
            $moves = $repo->findByFEN($fen, $games);

            $totalGames = $ply > 0 ? intval($form->get('totalGames')->getData()) : $games;

            $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

            $even = true;
            $selectedMultiplier = array_reduce($selectedPercentHistory, function ($carry, $number) use (&$even) {
                $even = !$even;
                return $even ? $carry * $number : $carry / $number;
            }, 1);

            $movesReached = $notationRepo->findByFENFromCourse($fen, $course, 'SAN');

            if ($myTurn) {
                usort($moves, function ($move1, $move2) use ($mMoves, $movesReached) {
                    if (array_key_exists($move1->getSan(), $movesReached) xor array_key_exists($move2->getSan(), $movesReached)) {
                        return array_key_exists($move1->getSan(), $movesReached) ? -1 : 1;
                    }
                    $mMove1 = array_key_exists($move1->getSan(), $mMoves) ? $mMoves[$move1->getSan()] : null;
                    $mMove2 = array_key_exists($move2->getSan(), $mMoves) ? $mMoves[$move2->getSan()] : null;
                    $mastersGames1 = $mMove1 ? $mMove1->getTotal() : 0;
                    $mastersGames2 = $mMove2 ? $mMove2->getTotal() : 0;
                    if ($mastersGames1 === $mastersGames2) {
                        return $move2->getTotal() - $move1->getTotal();
                    }
                    return $mastersGames2 - $mastersGames1;
                });
            } else {
                usort($moves, function ($move1, $move2) {
                    return $move2->getTotal() - $move1->getTotal();
                });
            }

            if (isset($fromFEN) && isset($fromSAN) && !$canSave && !$myTurn) {
                $variationsReached = $variationRepo->findByMoveFromCourse($fromFEN, $fromSAN, $course);

                if (empty($variationsReached)) {
                    $canSave = true;
                }
            }

            $movesForms = [];
            $FENsReached = [];

            foreach ($moves as $move) {

                $SAN = $move->getSan();

                $mMove = array_key_exists($SAN, $mMoves) ? $mMoves[$SAN] : null;

                $board = FenToBoardFactory::create($fen);
                $board->play($board->turn, $SAN);

                $FENReached = $board->toFen();

                $last = end($board->history);
                $lan = $last['from'] . $last['to'];

                $moveSelectedPercentHistory = $selectedPercentHistory;

                if ($myTurn) {
                    $cover = isset($mMove) && $mMove->getTotal() > $mastersGames / 100;
                    array_push($moveSelectedPercentHistory, $games, $move->getTotal());
                } else {
                    $cover = $move->getTotal() > $totalGames * $selectedMultiplier / $course->getCoverage();
                }

                if ($cover) {
                    $mbService->preloadMoves($FENReached);
                }

                $form = $formFactory->createNamed("build_move_$lan", BuildMoveType::class, [
                    'fromFEN' => $myTurn ? $fen : $fromFEN,
                    'fromSAN' => $myTurn ? $SAN : $fromSAN,
                    'ply' => $ply + 1,
                    'san' => $move->getSan(),
                    'selectedPercentHistory' => $moveSelectedPercentHistory,
                    'totalGames' => $totalGames,
                    'canSave' => $canSave,
                ], [
                    'action' => $this->generateUrl('app_course_build_moves_from_lan', ['id' => $course->getId(), 'fen' => $FENReached, 'fromLan' => $lan]),
                ]);

                $movesForm = [
                    'move' => $move,
                    'masters_games' => $mMove ? $mMove->getTotal() : 0,
                    'cover' => $cover,
                    'form' => $form->createView(),
                    'lan' => $lan,
                    'FENReached' => $FENReached,
                    'reached' => array_key_exists($move->getSan(), $movesReached),
                    'nextMoveReached' => false,
                ];

                $FENsReached[] = $FENReached;
                $movesForms[] = $movesForm;
            }

            if ($canSave) {
                $variation = new Variation();
                $variation->setCourse($course);
                $saveForm = $this->createForm(VariationFromPGNMovesType::class, $variation, [
                    'action' => $this->generateUrl('app_variation_new'),
                ]);


                if (!$myTurn) {
                    $nextVariationsReached = $notationRepo->findByFENFromCourse($FENsReached, $course, 'FEN');
                    foreach ($movesForms as &$movesForm) {
                        $movesForm['nextMoveReached'] = array_key_exists($movesForm['FENReached'], $nextVariationsReached);
                    }
                }
            }

            return $this->render('course/build_moves.html.twig', [
                'course' => $course,
                'fen' => $fen,
                'my_turn' => $myTurn,
                'games' => $myTurn ? $mastersGames : $totalGames * $selectedMultiplier,
                'moves_forms' => $movesForms,
                'save_form' => $saveForm ?? null,
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
