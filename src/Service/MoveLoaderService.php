<?php

namespace App\Service;

use App\Entity\MovePopularity;
use App\Entity\MovePopularityMasters;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use Chess\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MoveLoaderService
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MovePopularityRepository $repo,
        private readonly MovePopularityMastersRepository $mastersRepo,
        private readonly LichessApiService $lichessApi,
        private readonly MessageBusInterface $bus,
        LoggerInterface $lichessApiLogger,
    ) {
        $this->logger = $lichessApiLogger;
    }

    public function loadMastersMoves(string $fen, ?int &$nbGames = 0)
    {
        $responseMoves = $this->lichessApi->getMastersMoves($fen);

        if (isset($responseMoves)) {

            /**
             * @var array<string,MovePopularityMasters> $movesSaved
             */
            $movesSaved = array_reduce($this->mastersRepo->findBy(['since' => '2021', 'until' => '2024', 'fen' => $fen]), function ($carry, $move) {
                $carry[$move->getLan()] = $move;
                return $carry;
            }, []);

            $date = new \DateTime();
            $moves = [];
            foreach ($responseMoves as $move) {

                if ($move['san'] !== '-') {
                    $board = FenToBoardFactory::create($fen);
                    $board->play($board->turn, $move['san']);
                    $last = end($board->history);
                    $lan = $last['from'] . $last['to'];
                } else {
                    $lan = '-';
                }

                $movePopularity = isset($movesSaved[$lan]) ? $movesSaved[$lan] : new MovePopularityMasters();
                $movePopularity->setFen($fen);
                $movePopularity->setLan($lan);
                $movePopularity->setDateCreated($date);
                $movePopularity->setWhite($move['white']);
                $movePopularity->setBlack($move['black']);
                $movePopularity->setDraws($move['draws']);

                if (isset($move['opening']) && isset($move['opening']['name'])) {
                    $movePopularity->setOpening($move['opening']['name']);
                }

                $this->em->persist($movePopularity);

                if ($movePopularity->getLan() === '-') {
                    $nbGames = $movePopularity->getTotal() ?? 0;
                }
                $moves[$movePopularity->getLan()] = $movePopularity;
            }

            $this->em->flush();

            $this->logger->info("Loaded masters moves from fen $fen");

            return $moves;
        }

        return null;
    }

    public function loadAmateursMoves(string $fen, ?int &$nbGames = 0)
    {
        $responseMoves = $this->lichessApi->getLichessMoves($fen);

        if (isset($responseMoves)) {

            /**
             * @var array<string,MovePopularity> $movesSaved
             */
            $movesSaved = array_reduce($this->repo->findBy(['speeds' => 'rapid', 'ratings' => '1600,1800', 'since' => '2021-01', 'until' => '2024-12', 'fen' => $fen]), function ($carry, $move) {
                $carry[$move->getLan()] = $move;
                return $carry;
            }, []);

            $date = new \DateTime();
            $moves = [];
            foreach ($responseMoves as $move) {

                if ($move['san'] !== '-') {
                    $board = FenToBoardFactory::create($fen);
                    $board->play($board->turn, $move['san']);
                    $last = end($board->history);
                    $lan = $last['from'] . $last['to'];
                } else {
                    $lan = '-';
                }

                $movePopularity = isset($movesSaved[$lan]) ? $movesSaved[$lan] : new MovePopularity();
                $movePopularity->setFen($fen);
                $movePopularity->setLan($lan);
                $movePopularity->setDateCreated($date);

                $movePopularity->setWhite($move['white'] ?? 0);
                $movePopularity->setBlack($move['black'] ?? 0);
                $movePopularity->setDraws($move['draws'] ?? 0);

                $this->em->persist($movePopularity);

                if ($movePopularity->getLan() === '-') {
                    $nbGames = $movePopularity->getTotal() ?? 0;
                } else {
                    $moves[$movePopularity->getLan()] = $movePopularity;
                }
            }

            $this->em->flush();

            $this->logger->info("Loaded amateurs moves from fen $fen");

            return $moves;
        }

        return null;
    }
}
