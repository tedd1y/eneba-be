<?php

namespace App\Enums;

enum ProductType: string
{
    case Game = 'game';
    case Dlc = 'dlc';
    case GiftCard = 'gift_card';
    case Subscription = 'subscription';
    case InGameCurrency = 'in_game_currency';

    public function label(): string
    {
        return match ($this) {
            self::Game => 'Game',
            self::Dlc => 'DLC',
            self::GiftCard => 'Gift Card',
            self::Subscription => 'Subscription',
            self::InGameCurrency => 'In-Game Currency',
        };
    }
}
