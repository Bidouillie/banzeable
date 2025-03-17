<?php

namespace App\Message;

final class LoadMovesOptional extends LoadMoves
{
    public function __construct(
        public string $fen,
    ) {
        $this->fens = [$fen];
        $this->masters = false;
    }
}
