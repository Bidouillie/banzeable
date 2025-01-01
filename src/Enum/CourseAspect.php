<?php

namespace App\Enum;

enum CourseAspect: string
{
    case Opening = 'opening';
    case Endgame = 'endgame';
    case Strategy = 'strategy';
    case Tactics = 'tactics';
    case Repertoire = 'repertoire';
    
    public function getLabel(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Endgame => 'Endgame',
            self::Strategy => 'Strategy',
            self::Tactics => 'Tactics',
            self::Repertoire => 'Repertoire',
        };
    }
}