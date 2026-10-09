<?php

namespace App\Enums;

enum AuctionType: int
{
    /** Ascending: the price goes up with every bid. */
    case Up = 1;

    /** Descending (Dutch): the price drops on a timer until someone buys. */
    case Down = 2;

    public function label(): string
    {
        return match ($this) {
            self::Up => 'Đấu giá xuôi',
            self::Down => 'Đấu giá ngược',
        };
    }
}
