<?php

namespace App\MessageHandler;

use App\Entity\Course;
use App\Entity\Move;
use App\Message\PreloadMoves;
use App\Repository\CourseRepository;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use App\Service\MoveLoaderService;
use Chess\Variant\Classical\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PreloadMovesHandler
{
    public function __construct(
        private CourseRepository $courseRepo,
        private MovePopularityRepository $mpRepo,
        private MovePopularityMastersRepository $mpMastersRepo,
        private EntityManagerInterface $em,
        private MoveBuilderService $mbService,
        private MoveLoaderService $mlService,
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PreloadMoves $message): void
    {
        /**
         * @var Course $course
         */
        $course = $this->courseRepo->find($message->courseId);
        $baseFen = $message->baseFen;

        $lanMoves = explode(' ', $message->lanMoves);
        $movesPlayed = [];
        foreach ($lanMoves as $lan) {
            $moveObject = new Move();
            $moveObject->setLan($lan);
            $movesPlayed[] = $moveObject;
        }

        $movesSavedByFen = $course->getRepertoireMovesByFen();

        $expectedPercentage = $this->mbService->populateMoves($course, $baseFen, $movesPlayed, $newMovesPlayed, $movePopularitiesByFenLan);

        if (!isset($expectedPercentage)) {
            throw new \Exception("Missing expected percentage");
        }

        $fen = empty($movesPlayed) ? $baseFen : end($movesPlayed)->getFenTo();

        if (isset($movePopularitiesByFenLan[$fen])) {
            $movesPopularitiesByLan = $movePopularitiesByFenLan[$fen]['moves'];
            $nbGames = $movePopularitiesByFenLan[$fen]['nbGames'];
        } else {
            $movesPopularitiesByLan = $this->mpRepo->findGroupedByLan($fen, $nbGames);
        }

        $myTurn = ($course->isBlackOrientation() ? 'b' : 'w') === FenToBoardFactory::create($fen)->turn;

        $movePopularitiesMastersByLan = $myTurn ? $this->mpMastersRepo->findGroupedByLan($fen, $nbMastersGames) : null;

        if (($myTurn && !empty($movePopularitiesMastersByLan)) || (!$myTurn && !empty($movesPopularitiesByLan))) {

            $movesToPlay = $this->mbService->buildCandidateMoves($course, $fen, $movesSavedByFen, $movesPopularitiesByLan ?? [], $movePopularitiesMastersByLan ?? []);

            if ($myTurn) {
                $movesToPreload = array_filter($movesToPlay, function ($move) use ($nbMastersGames) {
                    return $move->getPopularityMasters() !== null && $move->getPopularityMasters()->getTotal() / $nbMastersGames > 1 / 100;
                });
            } else {
                $movesToPreload = array_filter($movesToPlay, function ($move) use ($expectedPercentage, $nbGames, $course) {
                    return $move->getPopularity() !== null && $expectedPercentage * $move->getPopularity()->getTotal() > $nbGames * $course->getTrueCoverage();
                });
            }

            $fensToPreload = array_map(function ($move) {
                return $move->getFenTo();
            }, $movesToPreload);

            $this->mlService->preloadMoves($fensToPreload, !$myTurn);

            if (!isset($movesPopularitiesByLan)) {
                $this->mlService->preloadMoves($fen, false);
                throw new \Exception("Missing popularity");
            }
        } elseif (!isset($movesPopularitiesByLan) || ($myTurn && !isset($movePopularitiesMastersByLan))) {
            $this->mlService->preloadMoves($fen, $myTurn);
            throw new \Exception("Missing popularity");
        }

        $this->hub->publish(new Update('course-builder', json_encode($message)));
    }
}
