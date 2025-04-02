<?php

namespace App\MessageHandler;

use App\Message\LoadMoves;
use App\Message\LoadMovesOptional;
use App\Message\LoadMovesRequired;
use App\Message\PreloadMyMoves;
use App\Message\PreloadOppMoves;
use App\Repository\MovePopularityMastersRepository;
use App\Repository\MovePopularityRepository;
use App\Service\MoveLoaderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class LoadMovesHandler
{
    public function __construct(
        private MovePopularityRepository $repo,
        private MovePopularityMastersRepository $mastersRepo,
        private MoveLoaderService $mlService,
        private HubInterface $hub,
        private LoggerInterface $logger,
        private LoggerInterface $lichessApiLogger,
    ) {}

    private function handleMessage(LoadMoves $message)
    {
        $info = "Loading moves from " . (count($message->fens) === 1 ? "fen " . reset($message->fens) : count($message->fens) . " fens");
        $this->lichessApiLogger->info($info);

        if ($message->amateurs) {

            $mpSaved = $this->repo->findSavedByFenGrouped($message->fens);

            $fensLoaded = $fensFailed = 0;
            foreach ($message->fens as $fen) {

                if (!isset($mpSaved[$fen])) {
                    if (!$this->mlService->loadAmateursMoves($fen)) {
                        throw new \Exception("Loading amateurs moves from fen $fen failed");
                    }
                    $fensLoaded++;
                } elseif (!$mpSaved[$fen]) {
                    $fensFailed++;
                }
            }

            $this->lichessApiLogger->info(sprintf("%d amateurs position%s loaded", $fensLoaded, $fensLoaded > 1 ? 's' : ''));

            if ($fensFailed > 0) {
                $this->lichessApiLogger->info(sprintf("%d amateurs position%s already failed", $fensFailed, $fensFailed > 1 ? 's' : ''));
            }
        }

        if ($message->masters) {

            $mpSavedMasters = $this->mastersRepo->findSavedByFenGrouped($message->fens);

            $fensLoaded = $fensFailed = 0;
            foreach ($message->fens as $fen) {

                if (!isset($mpSavedMasters[$fen])) {
                    if (!$this->mlService->loadMastersMoves($fen)) {
                        throw new \Exception("Loading masters moves from fen $fen failed");
                    }
                    $fensLoaded++;
                } elseif (!$mpSavedMasters[$fen]) {
                    $fensFailed++;
                }
            }

            $this->lichessApiLogger->info(sprintf("%d masters position%s loaded", $fensLoaded, $fensLoaded > 1 ? 's' : ''));

            if ($fensFailed > 0) {
                $this->lichessApiLogger->info(sprintf("%d masters position%s already failed", $fensFailed, $fensFailed > 1 ? 's' : ''));
            }
        }
    }

    #[AsMessageHandler]
    public function handleLoadMovesOptional(LoadMovesOptional $message): void
    {
        $this->handleMessage($message);

        try {
            $this->logger->info($this->hub->publish(new Update('course-builder', json_encode($message))));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    #[AsMessageHandler]
    public function handleLoadMovesRequired(LoadMovesRequired $message): void
    {
        $this->handleMessage($message);

        try {
            $this->logger->info($this->hub->publish(new Update('course-builder', json_encode($message))));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    #[AsMessageHandler]
    public function handlePreloadMyMoves(PreloadMyMoves $message): void
    {
        $this->handleMessage($message);
    }

    #[AsMessageHandler]
    public function handlePreloadOppMoves(PreloadOppMoves $message): void
    {
        $this->handleMessage($message);
    }
}
