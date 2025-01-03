<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\MovePopularity;
use App\Entity\MovePopularityMaster;
use App\Entity\User;
use App\Entity\Variation;
use App\Form\BuildMoveType;
use App\Form\StudyToggleType;
use App\Repository\CourseRepository;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use Chess\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
    public function buildMoves(#[MapEntity(id: 'id')] ?Course $course, ?string $fen, Request $request, MovePopularityRepository $repo, MovePopularityMasterRepository $masterRepo, EntityManagerInterface $em, HttpClientInterface $client): Response
    {
        $this->denyAccessUnlessGranted('course.owns', $course);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $moves = $repo->findByFEN($fen);

            $masterMoves = $masterRepo->findByFen($fen);

            $flush = false;

            if (count($masterMoves) < 1) {
                $flush = true;

                $response = $client->request('GET', 'https://explorer.lichess.ovh/masters', [
                    'query' => [
                        'fen' => $fen,
                        'since' => '2021',
                        'until' => '2024',
                        'moves' => 250,
                        'topGames' => 0,
                    ],
                ]);

                if ($response->getStatusCode() === 200) {

                    $content = $response->toArray();
                    $responseMoves = $content['moves'];
                    array_unshift($responseMoves, ['san' => '-', ...$content]);

                    $date = new \DateTime();

                    foreach ($responseMoves as $move) {
                        $movePopularity = new MovePopularityMaster();
                        $movePopularity->setSince('2021');
                        $movePopularity->setUntil('2024');
                        $movePopularity->setFEN($fen);
                        $movePopularity->setSan($move['san']);
                        $movePopularity->setDateCreated($date);
                        $movePopularity->setWhite($move['white']);
                        $movePopularity->setBlack($move['black']);
                        $movePopularity->setDraws($move['draws']);

                        if (isset($move['opening']) && isset($move['opening']['name'])) {
                            $movePopularity->setOpening($move['opening']['name']);
                        }

                        $em->persist($movePopularity);

                        $masterMoves[] = $movePopularity;
                    }
                }
            }

            if (count($moves) < 1) {
                $flush = true;

                $response = $client->request('GET', 'https://explorer.lichess.ovh/lichess', [
                    'query' => [
                        'fen' => $fen,
                        'variant' => 'standard',
                        'speeds' => 'rapid',
                        'ratings' => '1600,1800',
                        'since' => '2021-01',
                        'until' => '2024-12',
                        'moves' => 250,
                        'topGames' => 0,
                        'recentGames' => 0,
                    ],
                ]);

                if ($response->getStatusCode() === 200) {

                    $content = $response->toArray();
                    $responseMoves = $content['moves'];
                    array_unshift($responseMoves, ['san' => '-', ...$content]);

                    $date = new \DateTime();

                    foreach ($responseMoves as $move) {
                        $movePopularity = new MovePopularity();
                        $movePopularity->setVariant('standard');
                        $movePopularity->setSpeeds('rapid');
                        $movePopularity->setRatings('1600,1800');
                        $movePopularity->setSince('2021-01');
                        $movePopularity->setUntil('2024-12');
                        $movePopularity->setFEN($fen);
                        $movePopularity->setSan($move['san']);
                        $movePopularity->setDateCreated($date);
                        $movePopularity->setWhite($move['white']);
                        $movePopularity->setBlack($move['black']);
                        $movePopularity->setDraws($move['draws']);

                        $em->persist($movePopularity);

                        $moves[] = $movePopularity;
                    }
                }
            }

            if ($flush) {
                $em->flush();
            }

            $masterMoves = array_reduce($masterMoves, function ($carry, $item) {
                $carry[$item->getSan()] = $item;
                return $carry;
            }, []);
            
            usort($moves, function ($move1, $move2) use ($masterMoves) {
                $masterMove1 = isset($masterMoves[$move1->getSan()]) ? $masterMoves[$move1->getSan()] : null;
                $masterMove2 = isset($masterMoves[$move2->getSan()]) ? $masterMoves[$move2->getSan()] : null;
                $masterGames1 = $masterMove1 ? $masterMove1->getWhite() + $masterMove1->getBlack() + $masterMove1->getDraws() : 0;
                $masterGames2 = $masterMove2 ? $masterMove2->getWhite() + $masterMove2->getBlack() + $masterMove2->getDraws() : 0;
                return $masterGames2 - $masterGames1;
            });

            $movesForms = [];

            foreach ($moves as $move) {

                $masterMove = isset($masterMoves[$move->getSan()]) ? $masterMoves[$move->getSan()] : null;

                if ($move->getSan() !== '-') {
                    $board = FenToBoardFactory::create($fen);
                    $board->play($board->turn, $move->getSan());

                    $form = $this->createForm(BuildMoveType::class, null, [
                        'action' => $this->generateUrl('app_course_build_moves', ['id' => $course->getId(), 'fen' => $board->toFen()]),
                    ]);

                    $movesForms[] = [
                        'move' => $move,
                        'master_games' => $masterMove ? $masterMove->getWhite() + $masterMove->getBlack() + $masterMove->getDraws() : 0,
                        'form' => $form->createView(),
                    ];
                } else {
                    $games = $move->getWhite() + $move->getBlack() + $move->getDraws();
                    $masterGames = $masterMove ? $masterMove->getWhite() + $masterMove->getBlack() + $masterMove->getDraws() : 0;
                }
            }

            return $this->render('course/build_moves.html.twig', [
                'course' => $course,
                'fen' => $fen,
                'my_turn' => ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn,
                'games' => $games,
                'master_games' => $masterGames ?? 0,
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
