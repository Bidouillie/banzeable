<?php

namespace App\MessageHandler;

use App\Message\LoadEvaluations;
use App\Repository\PositionRepository;
use App\Service\MoveLoaderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class LoadEvaluationsHandler
{
    public function __construct(
        private PositionRepository $positionRepo,
        private MoveLoaderService $mlService,
        private HubInterface $hub,
        private LoggerInterface $logger,
        private LoggerInterface $lichessApiLogger,
    ) {}

    #[AsMessageHandler]
    public function handleMessage(LoadEvaluations $message)
    {
        $info = "Loading evaluations from " . (count($message->fens) === 1 ? "fen " . reset($message->fens) : count($message->fens) . " fens");
        $this->lichessApiLogger->info($info);

        $positions = $this->positionRepo->findGroupedByFen($message->fens);

        $fensLoaded = $fensFailed = 0;
        foreach ($message->fens as $fen) {
            if (!isset($positions[$fen]) || $positions[$fen]->getMate() === 0) {
                if (!$this->mlService->loadEvaluation($fen, $positions[$fen] ?? null)) {
                    throw new \Exception("Loading evaluation from fen $fen failed");
                }
                $fensLoaded++;
            } elseif (!$positions[$fen]) {
                $fensFailed++;
            }
        }

        $this->lichessApiLogger->info(sprintf("%d evaluation%s loaded", $fensLoaded, $fensLoaded > 1 ? 's' : ''));

        if ($fensFailed > 0) {
            $this->lichessApiLogger->info(sprintf("%d evaluation%s already failed", $fensFailed, $fensFailed > 1 ? 's' : ''));
        }

        if ($fensLoaded > 0) {
            try {
                $this->logger->info($this->hub->publish(new Update('course-builder', json_encode($message))));
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }
}
