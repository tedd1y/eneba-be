<?php

namespace App\Enums;

enum Platform: string
{
    case Steam = 'steam';
    case EpicGames = 'epic_games';
    case Gog = 'gog';
    case EaApp = 'ea_app';
    case UbisoftConnect = 'ubisoft_connect';
    case BattleNet = 'battle_net';
    case Xbox = 'xbox';
    case PlayStation = 'playstation';
    case Nintendo = 'nintendo';

    public function label(): string
    {
        return match ($this) {
            self::Steam => 'Steam',
            self::EpicGames => 'Epic Games',
            self::Gog => 'GOG',
            self::EaApp => 'EA App',
            self::UbisoftConnect => 'Ubisoft Connect',
            self::BattleNet => 'Battle.net',
            self::Xbox => 'Xbox',
            self::PlayStation => 'PlayStation',
            self::Nintendo => 'Nintendo',
        };
    }
}
