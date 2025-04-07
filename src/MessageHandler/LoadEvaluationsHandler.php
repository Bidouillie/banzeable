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

        $fensLoaded = 0;
        $fensFailed = [];
        foreach ($message->fens as $fen) {
            if (!isset($positions[$fen]) || $positions[$fen]->getMate() === 0) {
                if (!$this->mlService->loadEvaluation($fen, $positions[$fen] ?? null)) {
                    throw new \Exception("Loading evaluation from fen $fen failed");
                }
                $fensLoaded++;
            } elseif (!$positions[$fen]) {
                $fensFailed[] = $fen;
            }
        }

        $this->lichessApiLogger->info(sprintf("%d evaluation%s loaded", $fensLoaded, $fensLoaded > 1 ? 's' : ''));

        if (count($fensFailed) > 0) {
            $this->lichessApiLogger->warning(sprintf("%d evaluation%s already failed", count($fensFailed), count($fensFailed) > 1 ? 's' : ''));
            $this->lichessApiLogger->debug(implode(', ', $fensFailed));
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
