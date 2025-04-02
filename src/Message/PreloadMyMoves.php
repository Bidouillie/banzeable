<?php

namespace App\Message;

final class PreloadMyMoves extends LoadMoves
{
    public function __construct(
        public array $fens,
    ) {
        parent::__construct($fens, true, true);
    }
}
