<?php

namespace App\Message;

class LoadEvaluations
{
    /**
     * @param string[] $fens
     */
    public function __construct(
        public string $fen,
        public array $fens,
    ) {}
}
