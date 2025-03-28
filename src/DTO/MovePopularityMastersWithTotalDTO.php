<?php

namespace App\DTO;

class MovePopularityMastersWithTotalDTO
{
    /**
     * @param int $total
     */
    public function __construct(
        public readonly string $lan,
        public readonly ?int $total,
    ) {}
}
