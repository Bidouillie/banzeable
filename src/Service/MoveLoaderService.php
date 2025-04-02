<?php

namespace App\Service;

use App\Entity\MovePopularity;
use App\Entity\MovePopularityMasters;
use App\Entity\Position;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use App\Repository\PositionRepository;
use Chess\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MoveLoaderService
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MovePopularityRepository $mpRepo,
        private readonly MovePopularityMastersRepository $mpMastersRepo,
        private readonly PositionRepository $positionRepo,
        private readonly LichessApiService $lichessApi,
        private readonly MessageBusInterface $bus,
        LoggerInterface $lichessApiLogger,
    ) {
        $this->logger = $lichessApiLogger;
    }

    public function loadMastersMoves(string $fen)
    {
        $responseMoves = $this->lichessApi->getMastersMoves($fen);

        if (isset($responseMoves)) {

            $date = new \DateTime();
            foreach ($responseMoves as $move) {

                if ($move['san'] !== '-') {
                    $board = FenToBoardFactory::create($fen);
                    $board->play($board->turn, $move['san']);
                    $last = end($board->history);
                    $lan = $last['from'] . $last['to'];
                } else {
                    $lan = '-';
                }

                $movePopularity = new MovePopularityMasters();
                $movePopularity->setFen($fen);
                $movePopularity->setLan($lan);
                $movePopularity->setDateCreated($date);
                $movePopularity->setWhite($move['white']);
                $movePopularity->setBlack($move['black']);
                $movePopularity->setDraws($move['draws']);

                $movePopularity->setOpening($move['opening']['name'] ?? null);

                $this->em->persist($movePopularity);
            }

            $this->em->flush();

            $this->logger->info("Loaded masters moves from fen $fen");

            return true;
        }

        return null;
    }

    public function loadAmateursMoves(string $fen)
    {
        $responseMoves = $this->lichessApi->getAmateursMoves($fen);

        if (isset($responseMoves)) {

            $date = new \DateTime();
            foreach ($responseMoves as $move) {

                if ($move['san'] !== '-') {
                    $board = FenToBoardFactory::create($fen);
                    $board->play($board->turn, $move['san']);
                    $last = end($board->history);
                    $lan = $last['from'] . $last['to'];
                } else {
                    $lan = '-';
                }

                $movePopularity = new MovePopularity();
                $movePopularity->setFen($fen);
                $movePopularity->setLan($lan);
                $movePopularity->setDateCreated($date);

                $movePopularity->setWhite($move['white'] ?? 0);
                $movePopularity->setBlack($move['black'] ?? 0);
                $movePopularity->setDraws($move['draws'] ?? 0);

                $this->em->persist($movePopularity);
            }

            $this->em->flush();

            $this->logger->info("Loaded amateurs moves from fen $fen");

            return true;
        }

        return null;
    }

    public function loadEvaluation(string $fen, ?Position $position = null)
    {
        $position = $position ?? new Position();

        $response = $this->lichessApi->getEvaluation($fen);

        if (isset($response)) {

            if ($response['mate'] === 0) {
                $this->logger->error("Mate in 0 returned from lichess eval");
            } else {
                $position->setFen($fen);
                $position->setEvaluation(isset($response['cp']) ? number_format($response['cp'] / 100, 2) : null);
                $position->setMate($response['mate']);
            }

            $this->em->persist($position);

            $this->em->flush();

            $this->logger->info("Loaded evaluation from fen $fen");

            return true;
        }
    }
}
