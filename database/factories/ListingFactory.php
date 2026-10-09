<?php

namespace Database\Factories;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use App\Enums\SaleType;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * A running ascending auction that already exists in the engine.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'condition' => ItemCondition::Used,
            'auction_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'auction_type' => AuctionType::Up,
            'start_price' => 100_000,
            'end_time' => now()->addDay(),
            'auction_status' => AuctionStatus::Running,
            'current_price' => 100_000,
            'bid_count' => 0,
            'ends_at' => now()->addDay(),
        ];
    }

    public function status(AuctionStatus $status): static
    {
        return $this->state(fn () => ['auction_status' => $status]);
    }

    /**
     * A plain post with a price: no auction anywhere.
     */
    public function fixedPrice(): static
    {
        return $this->state(fn () => [
            'sale_type' => SaleType::Fixed,
            'auction_id' => null,
            'auction_type' => null,
            'end_time' => null,
            'ends_at' => null,
            'auction_status' => AuctionStatus::Running,
        ]);
    }

    public function descending(): static
    {
        return $this->state(fn () => [
            'auction_type' => AuctionType::Down,
            'end_time' => null,
            'down_time' => 60,
            'down_price' => 10_000,
            'finish_price' => 10_000,
        ]);
    }
}
