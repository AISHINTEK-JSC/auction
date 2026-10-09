<?php

namespace App\Services\AuctionEngine;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;

/**
 * Server-side client for the auction engine (/auction/sourcecode, /api/v1).
 *
 * The engine answers HTTP 200 for everything and reports failures in the body,
 * so every call goes through send(), which branches on "success".
 */
class AuctionEngineClient
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createAuction(array $params): array
    {
        return $this->send('post', 'auction', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuction(int $auctionId): array
    {
        return $this->send('get', "auction/{$auctionId}");
    }

    /**
     * Every auction of this tenant, as summaries.
     *
     * @return list<array<string, mixed>>
     */
    public function listAuctions(): array
    {
        return array_values($this->send('get', 'auctions'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBids(int $auctionId): array
    {
        try {
            return array_values($this->send('get', "auction/{$auctionId}/bids"));
        } catch (EngineException $e) {
            // Unpatched engines report "no bids yet" as an error.
            if ($e->is(EngineException::NO_BIDS)) {
                return [];
            }

            throw $e;
        }
    }

    /**
     * @return array{bid_id: int, bidder_id: string, bid_type: string, bid_price: int, bid_time: string}|null
     */
    public function getWinner(int $auctionId): ?array
    {
        return $this->send('get', "auction/{$auctionId}/winner")['bid_winner'] ?? null;
    }

    /**
     * For a market bid (no price) the engine computes the price itself, but it
     * reads bid_price in a log line before doing so and fails if the field is
     * missing. Always send one; 0 is overwritten by the engine.
     *
     * @return array<string, mixed>
     */
    public function placeBid(int $auctionId, int $bidderId, int $bidType, ?int $bidPrice = null): array
    {
        return $this->send('post', 'bid', [
            'auction_id' => $auctionId,
            'bidder_id' => $bidderId,
            'bid_type' => $bidType,
            'bid_price' => $bidPrice ?? 0,
        ]);
    }

    /**
     * Withdraw a bid from a running auction. The engine identifies a bid by its
     * bidder and price, not by an id.
     *
     * @return array<string, mixed>
     */
    public function cancelBid(int $auctionId, int $bidderId, int $bidPrice): array
    {
        return $this->send('put', 'bid/cancel-in-auction', [
            'auction_id' => $auctionId,
            'bidder_id' => $bidderId,
            'bid_price' => $bidPrice,
        ]);
    }

    /**
     * The winner of a finished auction backs out. All their bids are cancelled
     * and the next highest bidder, if any, becomes the winner; otherwise the
     * engine cancels the auction.
     *
     * @return array<string, mixed> auction_id, plus has_bid_successor and bid{bidder_id, bid_price} when the winner changed
     */
    public function withdrawWinner(int $auctionId, int $bidderId, int $bidPrice): array
    {
        return $this->send('put', 'bid/cancel-end-auction', [
            'auction_id' => $auctionId,
            'bidder_id' => $bidderId,
            'bid_price' => $bidPrice,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelAuction(int $auctionId): array
    {
        return $this->send('put', "auction/{$auctionId}/cancel");
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     */
    private function send(string $method, string $path, array $params = []): array
    {
        try {
            $response = $this->request()->{$method}($path, $params);
        } catch (ConnectionException $e) {
            throw new EngineException(__('engine.unreachable'), previous: $e);
        }

        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('success', $body)) {
            throw new EngineException(__('engine.unreachable'));
        }

        if ($body['success'] !== true) {
            throw new EngineException(
                $this->translate($body['error_code'] ?? null, (string) ($body['message'] ?? '')),
                $body['error_code'] ?? null,
                is_array($body['required'] ?? null) ? $body['required'] : [],
            );
        }

        return (array) ($body['data'] ?? []);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.auction_engine.url'), '/'))
            ->withToken((string) config('services.auction_engine.token'))
            ->acceptJson()
            ->asForm()
            ->timeout((int) config('services.auction_engine.timeout'));
    }

    /**
     * The engine only speaks Japanese. Map its error code to our own message and
     * carry over the numbers it interpolated (":0", ":1").
     */
    private function translate(?string $errorCode, string $message): string
    {
        if ($errorCode === null || ! Lang::has("engine.{$errorCode}")) {
            return $errorCode === null ? __('engine.failed') : $message;
        }

        preg_match_all('/\d[\d,.]*/', $message, $matches);

        $replace = [];
        foreach ($matches[0] as $index => $number) {
            $digits = preg_replace('/\D/', '', $number);
            $replace[":{$index}"] = $index === 0 && strlen((string) $digits) > 2
                ? number_format((int) $digits, 0, ',', '.')
                : $number;
        }

        $translation = __("engine.{$errorCode}");

        return is_string($translation) ? strtr($translation, $replace) : $message;
    }
}
