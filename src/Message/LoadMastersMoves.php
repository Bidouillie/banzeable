<?php

namespace App\Message;

final class LoadMastersMoves
{
    public function __construct(
        public readonly string $FEN,
    ) {}
}
