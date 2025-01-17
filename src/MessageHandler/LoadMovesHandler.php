<?php

namespace App\MessageHandler;

use App\Message\LoadMoves;
use App\Repository\MovePopularityRepository;
use App\Service\MoveBuilderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoadMovesHandler
{
    public function __construct(
        private MovePopularityRepository $repo,
        private EntityManagerInterface $em,
        private MoveBuilderService $mbService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LoadMoves $message): void
    {
        $this->logger->info("Handling LoadMoves $message->FEN");

        if (!$this->mbService->loadMoves($message->FEN)) {
            $this->logger->error("Handling LoadMoves $message->FEN failed");
        }
    }
}
