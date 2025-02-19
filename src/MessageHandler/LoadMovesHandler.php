<?php

namespace App\MessageHandler;

use App\Message\LoadMoves;
use App\Repository\MovePopularityRepository;
use App\Service\MoveLoaderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoadMovesHandler
{
    public function __construct(
        private MovePopularityRepository $repo,
        private EntityManagerInterface $em,
        private MoveLoaderService $mlService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LoadMoves $message): void
    {
        if (!$this->mlService->loadMoves($message->fen)) {
            $this->logger->error("Handling LoadMoves $message->fen failed");
        }
    }
}
