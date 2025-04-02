<?php

namespace App\Message;

final class LoadEvaluations
{
    /**
     * @param string[] $fens
     */
    public function __construct(
        public array $fens,
    ) {}
}
