<?php

namespace App\DTO;

class PositionEvaluationDTO
{
    public function __construct(
        public readonly string $fen,
        public readonly ?string $evaluation,
        public readonly ?int $mate,
    ) {}
}
