<?php

namespace App\EventListener;

use App\Message\LoadEvaluationsImportant;
use App\Message\LoadEvaluationsOptional;
use App\Message\LoadMovesImportant;
use App\Message\LoadMovesOptional;
use App\Message\LoadMovesRequired;
use App\Message\PreloadMyMoves;
use App\Message\PreloadOppMoves;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;

final class BuildMovesTerminateListener
{
    public function __construct(
        private LoggerInterface $logger,
        private MessageBusInterface $bus,
    ) {}

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        // $test = $request->query->getint('test', 1);

        if ($request->get('_route') === 'app_position_build_moves') {

            $response = $event->getResponse();

            $fen = strval($response->headers->get('X-Data-Fen'));
            $myTurn = boolval(json_decode($response->headers->get('X-Data-My-Turn')));

            $fenMovesLoad = $response->headers->get('X-Data-Load-Moves');
            $type = $response->headers->get('X-Data-Load-Moves-Type');

            $fenMovesPreload = $response->headers->get('X-Data-Preload-Moves');
            $fenEvals = $response->headers->get('X-Data-Load-Evals');

            $messages = [];

            if (isset($fenMovesLoad) && isset($type)) {
                $this->logger->debug("Fen moves to load with type $type");
                $this->logger->debug($fenMovesLoad);
                $fenMovesLoad = explode(',', $fenMovesLoad);
                switch ($type) {
                    case 'required':
                        $messages[] = new LoadMovesRequired($fen, $fenMovesLoad);
                        break;
                    case 'important':
                        $messages[] = new LoadMovesImportant($fen);
                        break;
                    case 'optional':
                        $messages[] = new LoadMovesOptional($fen);
                        break;
                }
            }

            if (isset($fenMovesPreload)) {
                $this->logger->debug("Fen moves to preload");
                $this->logger->debug($fenMovesPreload);
                $fenMovesPreload = explode(',', $fenMovesPreload);
                $messages[] = $myTurn ? new PreloadOppMoves($fenMovesPreload) : new PreloadMyMoves($fenMovesPreload);
            }

            if (isset($fenEvals)) {
                $this->logger->debug("Fen evals to load");
                $this->logger->debug($fenEvals);
                $fenEvals = explode(',', $fenEvals);
                $messages[] = $myTurn ? new LoadEvaluationsImportant($fen, $fenEvals) : new LoadEvaluationsOptional($fen, $fenEvals);
            }

            foreach ($messages as $message) {
                $this->bus->dispatch($message);
            }
        }
    }
}
