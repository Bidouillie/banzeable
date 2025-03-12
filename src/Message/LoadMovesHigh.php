<?php

namespace App\Message;

final class LoadMovesHigh
{
    public function __construct(
        public readonly string $fen,
    ) {}
}
