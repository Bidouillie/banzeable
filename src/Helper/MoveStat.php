<?php

namespace App\Helper;

use App\DTO\MovePopularityMastersWithTotalDTO;
use App\DTO\MovePopularityWithTotalDTO;
use App\Entity\Move;

class MoveStat
{
    public bool $saved = false;

    public bool $show = false;

    public ?MovePopularityWithTotalDTO $popularity;

    public ?MovePopularityMastersWithTotalDTO $popularityMasters;

    public ?float $selectedPercentage;

    public ?float $selectedPercentageMasters;

    public ?string $expectedPercentage;

    public ?float $completion;

    public ?float $eval;

    public ?bool $mate;

    public function __construct(public readonly Move $move) {}
}
