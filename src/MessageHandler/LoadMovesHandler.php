<?php

namespace App\MessageHandler;

use App\Entity\MovePopularity;
use App\Message\LoadMoves;
use App\Repository\MovePopularityRepository;
use App\Service\LichessApiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoadMovesHandler
{
    public function __construct(
        private MovePopularityRepository $repo,
        private EntityManagerInterface $em,
        private LichessApiService $lichessApi,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LoadMoves $message): void
    {
        $this->logger->info("Handling LoadMoves $message->FEN");

        $responseMoves = $this->lichessApi->getLichessMoves($message->FEN);

        if (isset($responseMoves)) {

            $moves = array_reduce($this->repo->findBy(['variant' => 'standard', 'speeds' => 'rapid', 'ratings' => '1600,1800', 'since' => '2021-01', 'until' => '2024-12', 'FEN' => $message->FEN]), function ($carry, $move) {
                $carry[$move->getSan()] = $move;
                return $carry;
            }, []);

            $date = new \DateTime();

            foreach ($responseMoves as $move) {
                $movePopularity = array_key_exists($move['san'], $moves) ? $moves[$move['san']] : new MovePopularity();
                $movePopularity->setVariant('standard');
                $movePopularity->setSpeeds('rapid');
                $movePopularity->setRatings('1600,1800');
                $movePopularity->setSince('2021-01');
                $movePopularity->setUntil('2024-12');
                $movePopularity->setFEN($message->FEN);
                $movePopularity->setSan($move['san']);
                $movePopularity->setDateCreated($date);
                $movePopularity->setWhite($move['white']);
                $movePopularity->setBlack($move['black']);
                $movePopularity->setDraws($move['draws']);

                $this->em->persist($movePopularity);
            }

            $this->em->flush();
        }
    }
}
