<?php

namespace App\Message;

final class PreloadMyMoves extends LoadMoves
{
    /**
     * @param string[] $fens
     */
    public function __construct(
        public array $fens,
    ) {
        $this->masters = null;
    }
}
