<?php

namespace App\DTO;

class MovePopularityWithTotalDTO
{
    /**
     * @param int $white
     * @param int $black
     * @param int $draws
     * @param int $total
     */
    public function __construct(
        public readonly string $fen,
        public readonly string $lan,
        public readonly ?int $white,
        public readonly ?int $black,
        public readonly ?int $draws,
        public readonly ?int $total,
    ) {}
}
