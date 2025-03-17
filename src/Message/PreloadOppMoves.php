<?php

namespace App\Message;

final class PreloadOppMoves extends LoadMoves
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
