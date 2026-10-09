<?php

namespace Tests\Feature\Marketplace;

use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuctionEngineClientTest extends TestCase
{
    use EngineFake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
    }

    public function test_it_sends_the_tenant_token_and_unwraps_data(): void
    {
        Http::fake(['engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7)))]);

        $auction = app(AuctionEngineClient::class)->getAuction(7);

        $this->assertSame(7, $auction['auction_id']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer engine-token'));
    }

    public function test_a_failure_in_a_200_response_becomes_an_exception_in_vietnamese(): void
    {
        Http::fake(['*' => Http::response($this->engineError('E_107', '現在価格より高い価格を入札してください 150000円.'))]);

        try {
            app(AuctionEngineClient::class)->placeBid(7, 3, 1, 100000);
            $this->fail('Expected an EngineException.');
        } catch (EngineException $e) {
            $this->assertSame('E_107', $e->errorCode);
            $this->assertSame('Giá đặt phải cao hơn giá hiện tại (150.000 đ).', $e->getMessage());
        }
    }

    public function test_validation_errors_are_exposed_per_field(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'The given data was invalid.',
            'required' => ['end_time' => ['The end time is required.']],
        ])]);

        try {
            app(AuctionEngineClient::class)->createAuction(['seller_id' => 1]);
            $this->fail('Expected an EngineException.');
        } catch (EngineException $e) {
            $this->assertSame(['end_time' => ['The end time is required.']], $e->fieldErrors);
        }
    }

    public function test_no_bids_is_an_empty_list_on_patched_and_unpatched_engines(): void
    {
        Http::fake(['engine.test/api/v1/auction/1/bids' => Http::response($this->engineOk([]))]);
        Http::fake(['engine.test/api/v1/auction/2/bids' => Http::response($this->engineError('E_102'))]);

        $this->assertSame([], app(AuctionEngineClient::class)->getBids(1));
        $this->assertSame([], app(AuctionEngineClient::class)->getBids(2));
    }

    public function test_an_unreachable_engine_becomes_an_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(EngineException::class);

        app(AuctionEngineClient::class)->getAuction(1);
    }

    public function test_market_bids_are_sent_form_encoded_with_a_placeholder_price(): void
    {
        Http::fake(['*' => Http::response($this->engineOk(['auction_id' => 7, 'bidder_id' => 3]))]);

        app(AuctionEngineClient::class)->placeBid(7, 3, 2);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid'
            && $request->isForm()
            && $request->data() === ['auction_id' => 7, 'bidder_id' => 3, 'bid_type' => 2, 'bid_price' => 0]);
    }
}
