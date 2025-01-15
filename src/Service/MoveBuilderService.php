<?php

namespace App\Service;

use App\Entity\MovePopularity;
use App\Entity\MovePopularityMaster;
use App\Message\LoadMastersMoves;
use App\Message\LoadMoves;
use App\Repository\MovePopularityMasterRepository;
use App\Repository\MovePopularityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MoveBuilderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MovePopularityRepository $repo,
        private readonly MovePopularityMasterRepository $masterRepo,
        private readonly MessageBusInterface $bus,
    ) {}

    public function preloadMoves(string|array $FENs, $masterFENSaved, $FENSaved)
    {
        if (!is_array($FENs)) {
            $FENs = [$FENs];
        }

        $messages = [];

        foreach ($FENs as $FEN) {
            if (!in_array($FEN, $masterFENSaved)) {
                $movePopularity = new MovePopularityMaster();
                $movePopularity->setFEN($FEN);
                $movePopularity->setSan('-');
                $movePopularity->setDateCreated(new \DateTime());

                $this->em->persist($movePopularity);
                $messages[] = new LoadMastersMoves($FEN);
            }
            if (!in_array($FEN, $FENSaved)) {
                $movePopularity = new MovePopularity();
                $movePopularity->setFEN($FEN);
                $movePopularity->setSan('-');
                $movePopularity->setDateCreated(new \DateTime());

                $this->em->persist($movePopularity);
                $messages[] = new LoadMoves($FEN);
            }
        }

        $this->em->flush();

        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }
}
