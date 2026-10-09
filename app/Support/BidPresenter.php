<?php

namespace App\Support;

use App\Models\User;

class BidPresenter
{
    /**
     * Attach bidder names to raw engine bids (the engine's bidder_id is our user id).
     *
     * @param  list<array<string, mixed>>  $bids
     * @return list<array{id: int, bidder: string, price: int, current_price: int, type: int, time: string}>
     */
    public static function present(array $bids): array
    {
        $names = User::query()
            ->whereKey(array_unique(array_map(fn (array $bid) => (int) $bid['bidder_id'], $bids)))
            ->pluck('name', 'id');

        return array_map(fn (array $bid) => [
            'id' => (int) $bid['bid_id'],
            'bidder' => $names[(int) $bid['bidder_id']] ?? 'Người dùng #'.$bid['bidder_id'],
            'price' => (int) $bid['bid_price'],
            'current_price' => (int) ($bid['current_price'] ?? $bid['bid_price']),
            'type' => (int) $bid['bid_type'],
            'time' => (string) $bid['bid_time'],
        ], $bids);
    }
}
