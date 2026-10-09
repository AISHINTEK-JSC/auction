<?php

namespace App\Enums;

enum BidType: int
{
    /** The bidder names a price. */
    case Limit = 1;

    /** The engine computes the price: next step for ascending, current price for descending. */
    case Market = 2;
}
