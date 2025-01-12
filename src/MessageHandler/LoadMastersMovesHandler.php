<?php

namespace App\MessageHandler;

use App\Entity\MovePopularityMaster;
use App\Message\LoadMastersMoves;
use App\Repository\MovePopularityMasterRepository;
use App\Service\LichessApiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoadMastersMovesHandler
{
    public function __construct(
        private MovePopularityMasterRepository $repo,
        private EntityManagerInterface $em,
        private LichessApiService $lichessApi,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LoadMastersMoves $message): void
    {
        $this->logger->info("Handling LoadMastersMoves $message->FEN");

        $responseMoves = $this->lichessApi->getMastersMoves($message->FEN);

        if (isset($responseMoves)) {

            $moves = array_reduce($this->repo->findBy(['since' => '2021', 'until' => '2024', 'FEN' => $message->FEN]), function ($carry, $move) {
                $carry[$move->getSan()] = $move;
                return $carry;
            }, []);

            $date = new \DateTime();

            foreach ($responseMoves as $move) {
                /**
                 * @var MovePopularityMaster $movePopularity
                 */
                $movePopularity = array_key_exists($move['san'], $moves) ? $moves[$move['san']] : new MovePopularityMaster();
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
