<?php

namespace App\Message;

final class PreloadMoves
{
    public function __construct(
        public readonly int $courseId,
        public readonly string $baseFen,
        public readonly string $lanMoves,
    ) {}
}
