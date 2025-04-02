<?php

namespace App\Message;

final class LoadMovesRequired extends LoadMoves
{
    public function __construct(
        public string $fen,
        array $fens,
    ) {
        parent::__construct($fens, true, false);
    }
}
