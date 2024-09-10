<?php

namespace App\Enum;

enum CourseType: string
{
    case Opening = 'opening';
    case Endgame = 'endgame';
    case Strategy = 'strategy';
    case Tactics = 'tactics';
    
    public function getLabel(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Endgame => 'Endgame',
            self::Strategy => 'Strategy',
            self::Tactics => 'Tactics',
        };
    }
}