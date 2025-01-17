<?php

namespace App\Service;

use App\Entity\MovePopularity;
use App\Entity\MovePopularityMaster;
use App\Message\LoadMastersMoves;
use App\Message\LoadMoves;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MoveBuilderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MovePopularityRepository $repo,
        private readonly MovePopularityMasterRepository $masterRepo,
        private readonly LichessApiService $lichessApi,
        private readonly MessageBusInterface $bus,
    ) {}

    public function loadMastersMoves(string $FEN, ?int &$nbGames = 0)
    {
        $responseMoves = $this->lichessApi->getMastersMoves($FEN);

        if (isset($responseMoves)) {

            $movesSaved = array_reduce($this->masterRepo->findBy(['since' => '2021', 'until' => '2024', 'FEN' => $FEN]), function ($carry, $move) {
                $carry[$move->getSan()] = $move;
                return $carry;
            }, []);

            $date = new \DateTime();
            $moves = [];
            foreach ($responseMoves as $move) {
                /**
                 * @var MovePopularityMaster $movePopularity
                 */
                $movePopularity = isset($movesSaved[$move['san']]) ? $movesSaved[$move['san']] : new MovePopularityMaster();
                $movePopularity->setFEN($FEN);
                $movePopularity->setSan($move['san']);
                $movePopularity->setDateCreated($date);
                $movePopularity->setWhite($move['white']);
                $movePopularity->setBlack($move['black']);
                $movePopularity->setDraws($move['draws']);

                if (isset($move['opening']) && isset($move['opening']['name'])) {
                    $movePopularity->setOpening($move['opening']['name']);
                }

                $this->em->persist($movePopularity);

                if ($movePopularity->getSan() === '-') {
                    $nbGames = $movePopularity->getTotal() ?? 0;
                }
                $moves[$movePopularity->getSan()] = $movePopularity;
            }

            $this->em->flush();

            return $moves;
        }
    }

    public function loadMoves(string $FEN, ?int &$nbGames = 0)
    {
        $responseMoves = $this->lichessApi->getLichessMoves($FEN);

        if (isset($responseMoves)) {

            $movesSaved = array_reduce($this->repo->findBy(['speeds' => 'rapid', 'ratings' => '1600,1800', 'since' => '2021-01', 'until' => '2024-12', 'FEN' => $FEN]), function ($carry, $move) {
                $carry[$move->getSan()] = $move;
                return $carry;
            }, []);

            $date = new \DateTime();
            $moves = [];
            foreach ($responseMoves as $move) {
                /**
                 * @var MovePopularity $movePopularity
                 */
                $movePopularity = isset($movesSaved[$move['san']]) ? $movesSaved[$move['san']] : new MovePopularity();
                $movePopularity->setFEN($FEN);
                $movePopularity->setSan($move['san']);
                $movePopularity->setDateCreated($date);
                $movePopularity->setWhite($move['white']);
                $movePopularity->setBlack($move['black']);
                $movePopularity->setDraws($move['draws']);

                $this->em->persist($movePopularity);

                if ($movePopularity->getSan() === '-') {
                    $nbGames = $movePopularity->getTotal() ?? 0;
                } else {
                    $moves[] = $movePopularity;
                }
            }

            $this->em->flush();

            return $moves;
        }
    }

    public function preloadMoves(string|array $FENs, $masterFENSaved, $FENSaved)
    {
        if (!is_array($FENs)) {
            $FENs = [$FENs];
        }

        $messages = [];

        foreach ($FENs as $FEN) {
            if (!in_array($FEN, $masterFENSaved)) {
                $movePopularity = new MovePopularityMaster();
                $movePopularity->setFEN($FEN);
                $movePopularity->setSan('-');
                $movePopularity->setDateCreated(new \DateTime());

                $this->em->persist($movePopularity);
                $messages[] = new LoadMastersMoves($FEN);
            }
            if (!in_array($FEN, $FENSaved)) {
                $movePopularity = new MovePopularity();
                $movePopularity->setFEN($FEN);
                $movePopularity->setSan('-');
                $movePopularity->setDateCreated(new \DateTime());

                $this->em->persist($movePopularity);
                $messages[] = new LoadMoves($FEN);
            }
        }

        $this->em->flush();

        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }
}
