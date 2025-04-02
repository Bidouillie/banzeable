<?php

namespace App\Controller;

use App\Message\LoadMovesOptional;
use Chess\Variant\Classical\Board;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {

        return $this->render('test/index.html.twig', [
            'controller_name' => 'TestController',
        ]);
    }

    private function reloadMoves(HubInterface $hub)
    {
        $hub->publish(new Update('course-builder', json_encode(new LoadMovesOptional('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -', true, true))));
    }

    private function checkCastleLan()
    {
        $board = new Board();
        $moves = ['e2e4', 'e7e5', 'g1f3', 'g8f6', 'f1d3', 'f8d6'];

        foreach ($moves as $move) {
            $board->playLan($board->turn, $move);
        }

        $board->playLan($board->turn, 'e1f1');

        $last = end($board->history);
        var_dump($last);
    }

    private function checkEnPassantSquare()
    {
        $board1 = new Board();
        $board2 = new Board();
        $moves1 = ['d4', 'd5', 'Nf3'];
        $moves2 = ['Nf3', 'd5', 'd4'];

        foreach ($moves1 as $move) {
            $board1->play($board1->turn, $move);
        }
        foreach ($moves2 as $move) {
            $board2->play($board2->turn, $move);
        }

        var_dump($board1->toFen()); // rnbqkbnr/ppp1pppp/8/3p4/3P4/5N2/PPP1PPPP/RNBQKB1R b KQkq -
        var_dump($board2->toFen()); // rnbqkbnr/ppp1pppp/8/3p4/3P4/5N2/PPP1PPPP/RNBQKB1R b KQkq d3
    }
}
