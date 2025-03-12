<?php

namespace App\MessageHandler;

use App\Message\LoadMoves;
use App\Message\LoadMovesHigh;
use App\Repository\MovePopularityRepository;
use App\Service\MoveLoaderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class LoadMovesHandler
{
    public function __construct(
        private MovePopularityRepository $repo,
        private EntityManagerInterface $em,
        private MoveLoaderService $mlService,
        private LoggerInterface $logger,
    ) {}

    private function handleMessage(LoadMoves | LoadMovesHigh $message)
    {
        if (!$this->mlService->loadMoves($message->fen)) {
            $this->logger->error("Handling LoadMoves $message->fen failed");
        }
    }

    #[AsMessageHandler]
    public function handleLoadMoves(LoadMoves $message): void
    {
        $this->handleMessage($message);
    }

    #[AsMessageHandler]
    public function handleLoadMovesHigh(LoadMovesHigh $message): void
    {
        $this->handleMessage($message);
    }
}
