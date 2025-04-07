<?php

namespace App\Message;

final class LoadMovesImportant extends LoadMoves
{
    public function __construct(
        public string $fen,
    ) {
        parent::__construct([$fen], false, true);
    }
}
