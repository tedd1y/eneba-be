<?php

namespace App\Enums;

enum Region: string
{
    case Global = 'global';
    case Europe = 'europe';
    case NorthAmerica = 'north_america';
    case UnitedKingdom = 'united_kingdom';
    case LatinAmerica = 'latin_america';
    case Asia = 'asia';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global',
            self::Europe => 'Europe',
            self::NorthAmerica => 'North America',
            self::UnitedKingdom => 'United Kingdom',
            self::LatinAmerica => 'Latin America',
            self::Asia => 'Asia',
        };
    }
}
