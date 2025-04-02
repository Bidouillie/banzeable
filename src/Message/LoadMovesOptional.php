<?php

namespace App\Message;

final class LoadMovesOptional extends LoadMoves
{
    public function __construct(
        public string $fen,
        bool $amateurs,
        bool $masters,
    ) {
        parent::__construct([$fen], $amateurs, $masters);
    }
}
