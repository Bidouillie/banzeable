<?php

namespace App\Message;

final class LoadMoves
{
    public function __construct(
        public readonly string $fen,
    ) {}
}
