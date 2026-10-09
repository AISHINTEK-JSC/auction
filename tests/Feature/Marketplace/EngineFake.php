<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Support\Facades\Http;

/**
 * Fakes the auction engine's HTTP API. The engine always answers 200 and
 * reports the outcome in the body.
 */
trait EngineFake
{
    protected function setUpEngine(): void
    {
        config([
            'services.auction_engine.url' => 'http://engine.test/api/v1',
            'services.auction_engine.token' => 'engine-token',
            'services.auction_engine.push_key' => 'push-key',
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function engineOk(array $data = []): array
    {
        return ['success' => true, 'message' => null, 'data' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    protected function engineError(string $code, string $message = 'エラー'): array
    {
        return ['success' => false, 'message' => $message, 'error_code' => $code];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function engineAuction(int $id, array $overrides = []): array
    {
        return [
            'auction_id' => $id,
            'auction_type' => '1',
            'current_price' => 100000,
            'bid_number' => 0,
            'start_time' => '2026-10-09 10:00:00',
            'end_time' => '2026-10-10 10:00:00',
            'status' => ['status' => 1, 'description' => ''],
            'highest_bidder_id' => null,
            'remaining_time' => 3600,
            ...$overrides,
        ];
    }
}
