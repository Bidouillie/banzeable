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
use App\Repository\MoveRepository;
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

        $FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -';

        $form = $this->createForm(
            BuildMoveType::class,
            [
                'selectedPercentHistory' => [],
                'canSave' => false,
            ],
            [
                'action' => $this->generateUrl('app_course_build_moves', ['id' => $course->getId(), 'FEN' => $FEN]),
            ]
        );

        return $this->render('course/build.html.twig', [
            'course' => $course,
            'course_encoded' => $serializer->serialize($course, 'json', ['groups' => ['Default']]),
            'form' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/course/{id}/build-moves/{FEN}', name: 'app_course_build_moves', requirements: ['id' => '\d+', 'FEN' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    #[Route('/course/{id}/build-moves/{FEN}/{LAN}', name: 'app_course_build_moves_from_lan', requirements: ['id' => '\d+', 'FEN' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$', 'lan' => '^([a-h][1-8]){2}$'])]
    public function buildMoves(#[MapEntity(id: 'id')] ?Course $course, ?string $FEN, ?string $LAN, Request $request, MovePopularityRepository $repo, MovePopularityMasterRepository $masterRepo, VariationRepository $variationRepo, MoveRepository $moveRepo, NotationRepository $notationRepo, MoveBuilderService $mbService, FormFactoryInterface $formFactory): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $formFactory->createNamed('build_move' . (isset($LAN) ? "_$LAN" : ''), BuildMoveType::class);
            $form->handleRequest($request);

            // TODO check if form submitted ?
            $data = $form->getData();
            $canSave = $data['canSave'];

            $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($FEN)->turn;

            $selectedMultiplier = empty($data['selectedPercentHistory']) ? 1 : end($data['selectedPercentHistory']);

            $movesReached = $notationRepo->findByFENFromCourse($FEN, $course, 'SAN');

            $moves = $repo->findByFEN($FEN, $nbGames);
            if(empty($moves)) {
                $moves = $mbService->loadMoves($FEN, $nbGames);
            }

            if ($myTurn) {
                $mMoves = $masterRepo->findByFen($FEN, $nbMastersGames);
                if(empty($mMoves)) {
                    $mMoves = $mbService->loadMastersMoves($FEN, $nbMastersGames);
                }

                usort($moves, function ($move1, $move2) use ($mMoves, $movesReached) {
                    if (isset($movesReached[$move1->getSan()]) xor isset($movesReached[$move2->getSan()])) {
                        return isset($movesReached[$move1->getSan()]) ? -1 : 1;
                    }
                    $mMove1 = isset($mMoves[$move1->getSan()]) ? $mMoves[$move1->getSan()] : null;
                    $mMove2 = isset($mMoves[$move2->getSan()]) ? $mMoves[$move2->getSan()] : null;
                    $nbMastersGames1 = $mMove1 ? $mMove1->getTotal() : 0;
                    $nbMastersGames2 = $mMove2 ? $mMove2->getTotal() : 0;
                    if ($nbMastersGames1 === $nbMastersGames2) {
                        return $move2->getTotal() - $move1->getTotal();
                    }
                    return $nbMastersGames2 - $nbMastersGames1;
                });
            } else {
                usort($moves, function ($move1, $move2) {
                    return $move2->getTotal() - $move1->getTotal();
                });
            }

            $nextFENs = array_map(function ($move) {
                return $move->getNextFEN();
            }, $moves);

            $masterNextFENsSaved = $masterRepo->findGroupedByFEN($nextFENs, ['since' => '2021', 'until' => '2024']);
            $nextFENsSaved = $repo->findGroupedByFEN($nextFENs, ['speeds' => 'rapid', 'ratings' => '1600,1800', 'since' => '2021-01', 'until' => '2024-12']);

            $nextVariationsReached = $moveRepo->findByFENReachedFromCourse($nextFENs, $course, 'FEN');

            if (isset($data['myLastTurnFEN']) && isset($data['myLastTurnSAN']) && !$canSave && !$myTurn) {
                $variationsReached = $variationRepo->findByMoveFromCourse($data['myLastTurnFEN'], $data['myLastTurnSAN'], $course);

                if (empty($variationsReached)) {
                    $canSave = true;
                }
            }

            if ($canSave) {
                $variation = new Variation();
                $variation->setCourse($course);
                $variation->setSelectedPercentHistory($data['selectedPercentHistory']);
                $saveForm = $this->createForm(VariationFromPGNMovesType::class, $variation, [
                    'action' => $this->generateUrl('app_variation_new'),
                ]);
            }

            $FENsToPreload = [];
            $movesForms = [];
            foreach ($moves as $move) {

                $nextLAN = $move->getLAN();
                $FENReached = $move->getNextFEN();

                $SAN = $move->getSan();

                $cover = false;
                $expected = 0;
                if ($myTurn) {
                    if (isset($mMoves) && isset($mMoves[$SAN])) {
                        $cover = $mMoves[$SAN]->getTotal() > $nbMastersGames / 100;
                        $expected = isset($nbMastersGames) && $nbMastersGames > 0 ? $mMoves[$SAN]->getTotal() / $nbMastersGames : 0;
                    }
                } else {
                    $cover = $selectedMultiplier * $move->getTotal() / $nbGames > 1 / $course->getCoverage();
                    $expected = $selectedMultiplier * $move->getTotal() / $nbGames;
                }

                $moveSelectedPercentHistory = $data['selectedPercentHistory'];

                $moveSelectedPercent = $selectedMultiplier * ($myTurn ? 1 : $move->getTotal() / $nbGames);
                if (!isset($movesReached[$SAN]) && isset($nextVariationsReached[$FENReached])) {
                    $moveSelectedPercent += $nextVariationsReached[$FENReached]->getSelectedMultiplier();
                }

                array_push($moveSelectedPercentHistory, $moveSelectedPercent);

                if ($cover) {
                    $FENsToPreload[] = $FENReached;
                }

                $form = $formFactory->createNamed("build_move_$nextLAN", BuildMoveType::class, [
                    'myLastTurnFEN' => $myTurn ? $FEN : $data['myLastTurnFEN'],
                    'myLastTurnSAN' => $myTurn ? $SAN : $data['myLastTurnSAN'],
                    'selectedPercentHistory' => $moveSelectedPercentHistory,
                    'canSave' => $canSave,
                ], [
                    'action' => $this->generateUrl('app_course_build_moves_from_lan', ['id' => $course->getId(), 'FEN' => $FENReached, 'LAN' => $nextLAN]),
                ]);

                $movesForms[] = [
                    'move' => $move,
                    'form' => $form->createView(),
                    'cover' => $cover,
                    'expected' => $expected,
                    'reached' => isset($movesReached[$SAN]),
                    'next_move_reached' => isset($nextVariationsReached[$FENReached]),
                ];
            }

            $mbService->preloadMoves($FENsToPreload, $masterNextFENsSaved, $nextFENsSaved);

            return $this->render('course/build_moves.html.twig', [
                'course' => $course,
                'my_turn' => $myTurn,
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
