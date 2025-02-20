<?php

namespace App\MessageHandler;

use App\Message\LoadMastersMoves;
use App\Repository\MovePopularityMastersRepository;
use App\Service\MoveLoaderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoadMastersMovesHandler
{
    public function __construct(
        private MovePopularityMastersRepository $repo,
        private EntityManagerInterface $em,
        private MoveLoaderService $mlService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LoadMastersMoves $message): void
    {
        if (!$this->mlService->loadMastersMoves($message->fen)) {
            $this->logger->error("Handling LoadMastersMoves $message->fen failed");
        }
    }
}
