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

    public function preloadMoves($fen)
    {
        $messages = [];

        if ($this->masterRepo->findOneBy(['since' => '2021', 'until' => '2024', 'FEN' => $fen]) === null) {
            $movePopularity = new MovePopularityMaster();
            $movePopularity->setFEN($fen);
            $movePopularity->setSan('-');
            $movePopularity->setDateCreated(new \DateTime());

            $this->em->persist($movePopularity);
            $messages[] = new LoadMastersMoves($fen);
        }
        if ($this->repo->findOneBy(['speeds' => 'rapid', 'ratings' => '1600,1800', 'since' => '2021-01', 'until' => '2024-12', 'FEN' => $fen]) === null) {
            $movePopularity = new MovePopularity();
            $movePopularity->setFEN($fen);
            $movePopularity->setSan('-');
            $movePopularity->setDateCreated(new \DateTime());

            $this->em->persist($movePopularity);
            $messages[] = new LoadMoves($fen);
        }
        
        $this->em->flush();

        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }
}
