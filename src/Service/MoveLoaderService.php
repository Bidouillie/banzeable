<?php

namespace App\Service;

use App\Entity\MovePopularity;
use App\Entity\MovePopularityMaster;
use App\Message\LoadMastersMoves;
use App\Message\LoadMoves;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use Chess\FenToBoardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MoveLoaderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MovePopularityRepository $repo,
        private readonly MovePopularityMasterRepository $masterRepo,
        private readonly LichessApiService $lichessApi,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    public function loadMastersMoves(string $fen, ?int &$nbGames = 0)
    {
        $responseMoves = $this->lichessApi->getMastersMoves($fen);

        if (isset($responseMoves)) {

            /**
             * @var array<string,MovePopularityMaster> $movesSaved
             */
            $movesSaved = array_reduce($this->masterRepo->findBy(['since' => '2021', 'until' => '2024', 'fen' => $fen]), function ($carry, $move) {
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

                $movePopularity = isset($movesSaved[$lan]) ? $movesSaved[$lan] : new MovePopularityMaster();
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

            return $moves;
        }
    }

    public function loadMoves(string $fen, ?int &$nbGames = 0)
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

            return $moves;
        }
    }

    public function preloadMoves(string|array $fens, bool $masters)
    {
        $fensSaved = $this->repo->findByFenGrouped($fens);
        $masterFensSaved = $masters ? $this->masterRepo->findByFenGrouped($fens) : null;

        if (!is_array($fens)) {
            $fens = [$fens];
        }

        $messages = [];

        foreach ($fens as $fen) {

            if ($masters && !isset($masterFensSaved[$fen])) {
                $flush = true;
                $movePopularity = new MovePopularityMaster();
                $movePopularity->setFen($fen);
                $movePopularity->setLan('-');
                $movePopularity->setDateCreated(new \DateTime());

                $this->em->persist($movePopularity);
                $messages[] = new LoadMastersMoves($fen);
            }
            if (!isset($fensSaved[$fen])) {
                $flush = true;
                $movePopularity = new MovePopularity();
                $movePopularity->setFen($fen);
                $movePopularity->setLan('-');
                $movePopularity->setDateCreated(new \DateTime());

                $this->em->persist($movePopularity);
                $messages[] = new LoadMoves($fen);
            }
        }

        if (isset($flush)) {
            $this->em->flush();
        }

        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }
}
