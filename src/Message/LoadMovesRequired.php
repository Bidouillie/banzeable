<?php

namespace App\Message;

final class LoadMovesRequired extends LoadMoves
{
    /**
     * @param string[] $fens
     */
    public function __construct(
        public array $fens,
    ) {
        $this->masters = false;
    }
}
