<?php

namespace App\Message;

final class PreloadOppMoves extends LoadMoves
{
    public function __construct(
        public array $fens,
    ) {
        parent::__construct($fens, true, false);
    }
}
