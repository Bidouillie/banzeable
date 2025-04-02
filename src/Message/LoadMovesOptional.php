<?php

namespace App\Message;

final class LoadMovesOptional extends LoadMoves
{
    public function __construct(
        public string $fen,
        public bool $amateurs,
        public bool $masters,
    ) {
        parent::__construct([$fen], $amateurs, $masters);
    }
}
