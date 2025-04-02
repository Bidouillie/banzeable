<?php

namespace App\DTO;

class MovePopularityWithTotalFenDTO extends MovePopularityWithTotalDTO
{
    public function __construct(
        public readonly string $fen,
        string $lan,
        ?int $white,
        ?int $black,
        ?int $draws,
        ?int $total,
    ) {
        parent::__construct($lan, $white, $black, $draws, $total);
    }
}
