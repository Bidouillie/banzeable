<?php

namespace App\MessageHandler;

use App\Entity\MovePopularityMaster;
use App\Message\LoadMastersMoves;
use App\Service\LichessApiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class LoadMastersMovesHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private LichessApiService $lichessApi,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LoadMastersMoves $message): void
    {
        $this->logger->info("Handling LoadMastersMoves $message->FEN");

        $responseMoves = $this->lichessApi->getMastersMoves($message->FEN);

        if (isset($responseMoves)) {

            $date = new \DateTime();

            foreach ($responseMoves as $move) {
                $movePopularity = new MovePopularityMaster();
                $movePopularity->setSince('2021');
                $movePopularity->setUntil('2024');
                $movePopularity->setFEN($message->FEN);
                $movePopularity->setSan($move['san']);
                $movePopularity->setDateCreated($date);
                $movePopularity->setWhite($move['white']);
                $movePopularity->setBlack($move['black']);
                $movePopularity->setDraws($move['draws']);

                if (isset($move['opening']) && isset($move['opening']['name'])) {
                    $movePopularity->setOpening($move['opening']['name']);
                }

                $this->em->persist($movePopularity);
            }

            $this->em->flush();
        }
    }
}
