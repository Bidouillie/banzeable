<?php

namespace App\MessageHandler;

use App\Message\LoadMastersMoves;
use App\Repository\MovePopularityMasterRepository;
use App\Service\MoveBuilderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoadMastersMovesHandler
{
    public function __construct(
        private MovePopularityMasterRepository $repo,
        private EntityManagerInterface $em,
        private MoveBuilderService $mbService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LoadMastersMoves $message): void
    {
        $this->logger->info("Handling LoadMastersMoves $message->FEN");

        if (!$this->mbService->loadMastersMoves($message->FEN)) {
            $this->logger->error("Handling LoadMastersMoves $message->FEN failed");
        }
    }
}
