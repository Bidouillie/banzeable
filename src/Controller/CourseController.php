<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Entity\Variation;
use App\Form\BuildMoveType;
use App\Form\MoveBuilderVariationType;
use App\Form\StudyToggleType;
use App\Repository\CourseRepository;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use App\Repository\MoveRepository;
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

#[Route('/course')]
class CourseController extends AbstractController
{
    #[Route('/index', name: 'app_course')]
    public function index(CourseRepository $repo): Response
    {
        $courses = $repo->findAll();

        return $this->render('course/index.html.twig', [
            'courses' => $courses,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/{id}', name: 'app_course_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
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
    #[Route('/owned', name: 'app_course_owned')]
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
    #[Route('/{id}/build', name: 'app_course_build', requirements: ['id' => '\d+'])]
    public function build(#[MapEntity(id: 'id')] ?Course $course, SerializerInterface $serializer): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        $FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -';

        $form = $this->createForm(
            BuildMoveType::class,
            null,
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
    #[Route('/{id}/build-moves/{FEN}', name: 'app_course_build_moves', requirements: ['id' => '\d+', 'FEN' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$'])]
    #[Route('/{id}/build-moves/{FEN}/{LAN}', name: 'app_course_build_moves_from_lan', requirements: ['id' => '\d+', 'FEN' => '^([1-8pnbrqkPNBRQK]+\/){7}[1-8pnbrqkPNBRQK]+ [wb] (K?Q?k?q?|-)( ([a-h][1-8]|-))?$', 'lan' => '^([a-h][1-8]){2}$'])]
    public function buildMoves(#[MapEntity(id: 'id')] ?Course $course, ?string $FEN, ?string $LAN, Request $request, MovePopularityRepository $mpRepo, MovePopularityMasterRepository $mpMasterRepo, MoveRepository $moveRepo, MoveBuilderService $mbService, FormFactoryInterface $formFactory): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $form = $formFactory->createNamed('build_move' . (isset($LAN) ? "_$LAN" : ''), BuildMoveType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($FEN)->turn;

                $FENHistory = $form->get('FENHistory')->getData();
                $LANHistory = $form->get('LANHistory')->getData();

                $selectedPercentHistory = $form->get('selectedPercentHistory')->getData();
                $totalSelectedPercent = 1;
                $totalSelectedPercentHistory = array_map(function ($selectedPercent) use (&$totalSelectedPercent) {
                    $totalSelectedPercent *= $selectedPercent;
                    return $totalSelectedPercent;
                }, $selectedPercentHistory);

                $canSaveHistory = $form->get('canSaveHistory')->getData();
                $canSave = empty($canSaveHistory) ? false : end($canSaveHistory);

                $movesMerged = $form->get('movesMerged')->getData();
                $lastMovesMerged = array_reduce($movesMerged, function ($carry, $moveMerged) {
                    $carry[$moveMerged['move']->getVariation()->getId()] = $moveMerged;
                    return $carry;
                }, []);

                $movesSaved = $moveRepo->findByFENFromCourse($FEN, $course, 'SAN');

                $moves = $mpRepo->findByFEN($FEN, $nbGames);
                if (empty($moves)) {
                    $moves = $mbService->loadMoves($FEN, $nbGames);
                }

                if ($myTurn) {
                    $mMoves = $mpMasterRepo->findByFen($FEN, $nbMastersGames);
                    if (empty($mMoves)) {
                        $mMoves = $mbService->loadMastersMoves($FEN, $nbMastersGames);
                    }

                    usort($moves, function ($move1, $move2) use ($mMoves, $movesSaved) {
                        if (isset($movesSaved[$move1->getSan()]) xor isset($movesSaved[$move2->getSan()])) {
                            return isset($movesSaved[$move1->getSan()]) ? -1 : 1;
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

                $masterNextFENsSaved = $mpMasterRepo->findGroupedByFEN($nextFENs, ['since' => '2021', 'until' => '2024']);
                $nextFENsSaved = $mpRepo->findGroupedByFEN($nextFENs, ['speeds' => 'rapid', 'ratings' => '1600,1800', 'since' => '2021-01', 'until' => '2024-12']);

                $movesSavedFENReached = $moveRepo->findByFENReachedFromCourse($nextFENs, $course, 'FEN');
                $nextMovesPlayed = array_reduce(array_keys($movesSavedFENReached), function ($carry, $FENReached) use ($movesSavedFENReached) {
                    $carry[$FENReached] = array_reduce($movesSavedFENReached[$FENReached], function ($carry, $move) {
                        $carry[$move->getNotation()->getFEN()] = $move;
                        return $carry;
                    }, []);
                    return $carry;
                }, []);

                $FENsToPreload = [];
                $movesForms = [];
                foreach ($moves as $move) {

                    $SAN = $move->getSan();
                    $nextLAN = $move->getLAN();
                    $FENReached = $move->getNextFEN();

                    $moveCanSave = $canSave || !isset($nextMovesPlayed[$FENReached]) || !isset($nextMovesPlayed[$FENReached][$FEN]);

                    /**
                     * Selected Percent
                     */
                    $moveSelectedPercent = isset($movesSaved[$SAN]) ? $movesSaved[$SAN]->getSelectedMultiplier() : ($myTurn ? 1 : $move->getTotal() / $nbGames);

                    $moveTotalSelectedPercent = $totalSelectedPercent * $moveSelectedPercent;

                    $moveMovesMerged = $movesMerged;

                    if (!isset($movesSaved[$SAN]) && isset($movesSavedFENReached[$FENReached])) {
                        $moveToMerge = $movesSavedFENReached[$FENReached][0];
                        if (isset($lastMovesMerged[$moveToMerge->getVariation()->getId()])) {
                            $lastMoveMerged = $lastMovesMerged[$moveToMerge->getVariation()->getId()];
                            $moveTotalSelectedPercent += $moveToMerge->getTotalSelectedMultiplier() * $totalSelectedPercentHistory[$lastMoveMerged['index']] / $lastMoveMerged['move']->getTotalSelectedMultiplier();
                        } else {
                            $moveTotalSelectedPercent += $moveToMerge->getTotalSelectedMultiplier();
                        }
                        $moveMovesMerged[] = ['move' => $moveToMerge, 'index' => count($selectedPercentHistory)];
                    }

                    $moveSelectedPercent = $moveTotalSelectedPercent / $totalSelectedPercent;


                    if ($myTurn) {
                        $expected = isset($nbMastersGames) && $nbMastersGames > 0 && isset($mMoves) && isset($mMoves[$SAN]) ? $mMoves[$SAN]->getTotal() / $nbMastersGames : 0;
                        $cover = $expected > 1 / 100;
                        if (isset($movesSaved[$SAN])) {
                            $cover = true;
                        }
                    } else {
                        $expected = $totalSelectedPercent * $move->getTotal() / $nbGames;
                        $cover = $expected > 1 / $course->getCoverage();
                    }

                    if ($cover) {
                        $FENsToPreload[] = $FENReached;
                    }

                    $form = $formFactory->createNamed("build_move_$nextLAN", BuildMoveType::class, [
                        'FENHistory' => [...$FENHistory, $FEN],
                        'LANHistory' => [...$LANHistory, $LAN],
                        'selectedPercentHistory' => [...$selectedPercentHistory, $moveSelectedPercent],
                        'movesMerged' => $moveMovesMerged,
                        'canSaveHistory' => [...$canSaveHistory, $moveCanSave],
                    ], [
                        'action' => $this->generateUrl('app_course_build_moves_from_lan', ['id' => $course->getId(), 'FEN' => $FENReached, 'LAN' => $nextLAN]),
                    ]);

                    $movesForms[] = [
                        'move' => $move,
                        'form' => $form->createView(),
                        'cover' => $cover,
                        'expected' => $expected,
                        'saved' => isset($movesSaved[$SAN]),
                        'next_saved' => isset($movesSavedFENReached[$FENReached]),
                    ];
                }

                $mbService->preloadMoves($FENsToPreload, $masterNextFENsSaved, $nextFENsSaved);

                if ($canSave && (prev($canSaveHistory) || !$myTurn)) {
                    $variation = new Variation();
                    $variation->setCourse($course);
                    $variation->setName('repertoire');
                    $variation->setBlackOrientation($course->isBlackOrientation());
                    $saveForm = $this->createForm(MoveBuilderVariationType::class, [
                        'variation' => $variation,
                        'selectedPercentHistory' => $selectedPercentHistory,
                        'movesMerged' => array_map(function ($moveMerged) {
                            return $moveMerged['move'];
                        }, $movesMerged),
                    ], [
                        'action' => $this->generateUrl('app_variation_new'),
                    ]);
                }

                $lastTurnFEN = array_pop($FENHistory);
                if (isset($lastTurnFEN) && !empty($selectedPercentHistory) && !empty($canSaveHistory)) {
                    array_pop($selectedPercentHistory);
                    array_pop($canSaveHistory);
                    $movesMerged = array_filter($movesMerged, function ($moveMerged) use ($selectedPercentHistory) {
                        return $moveMerged['index'] < count($selectedPercentHistory);
                    });

                    $lastTurnLAN = array_pop($LANHistory);
                    $formName = isset($lastTurnLAN) ? "build_move_$lastTurnLAN" : 'build_move';
                    $formAction = isset($lastTurnLAN) ? $this->generateUrl('app_course_build_moves_from_lan', ['id' => $course->getId(), 'FEN' => $lastTurnFEN, 'LAN' => $lastTurnLAN]) : $this->generateUrl('app_course_build_moves', ['id' => $course->getId(), 'FEN' => $lastTurnFEN]);
                    $previousForm = $formFactory->createNamed($formName, BuildMoveType::class, [
                        'FENHistory' => $FENHistory,
                        'LANHistory' => $LANHistory,
                        'selectedPercentHistory' => $selectedPercentHistory,
                        'movesMerged' => $movesMerged,
                        'canSaveHistory' => $canSaveHistory,
                    ], [
                        'action' => $formAction,
                    ]);
                }

                return $this->render('course/build_moves.html.twig', [
                    'course' => $course,
                    'my_turn' => $myTurn,
                    'moves_forms' => $movesForms,
                    'previous_form' => $previousForm ?? null,
                    'save_form' => $saveForm ?? null,
                ]);
            }
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
    #[Route('/{id}/study', name: 'app_study', requirements: ['id' => '\d+'])]
    #[Route('/{id}/study/{id_variation}', name: 'app_study_variation', requirements: ['id' => '\d+', 'id_variation' => '\d+'])]
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
