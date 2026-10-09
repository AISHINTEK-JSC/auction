<?php

namespace App\Enums;

enum SaleType: string
{
    /** Sold through an auction in the auction engine. */
    case Auction = 'auction';

    /** A plain post with a price: buyers contact the seller, nothing goes to the engine. */
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Auction => 'Đấu giá',
            self::Fixed => 'Giá cố định',
        };
    }
}
