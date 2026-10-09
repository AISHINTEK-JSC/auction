<?php

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Terms and privacy pages, and what the site tells search engines and
 * link previews: meta tags, sitemap, robots.txt.
 */
class SiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_terms_and_privacy_pages_are_open_to_everyone(): void
    {
        config(['marketplace.contact_email' => 'hotro@example.com']);

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/Terms')->where('contactEmail', 'hotro@example.com')->has('updatedAt'))
            ->assertSee('<meta name="robots" content="index, follow">', false);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/Privacy'))
            ->assertSee('Chính sách quyền riêng tư - '.config('app.name'), false);
    }

    public function test_public_pages_describe_themselves(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<meta name="description" content="Sàn đấu giá', false)
            ->assertSee('<meta property="og:site_name" content="'.config('app.name').'">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta property="og:image" content="'.url('og-image.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);

        $category = Category::factory()->create(['name' => 'Máy ảnh']);

        // Filters do not make a separate document: the canonical address has no query string.
        $this->get(route('categories.show', ['category' => $category, 'sort' => 'newest']))
            ->assertSee('<title>Máy ảnh - '.config('app.name').'</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('categories.show', $category).'">', false);
    }

    public function test_a_listing_page_carries_its_title_price_and_cover_image(): void
    {
        Storage::fake('public');

        $listing = Listing::factory()->fixedPrice()->create([
            'title' => 'Bàn gỗ "sồi" <cũ>',
            'description' => '<p>Bàn còn   tốt.</p><p>Giao tại nhà.</p>',
            'current_price' => 1500000,
        ]);
        $listing->images()->create(['path' => 'listings/1/cover.jpg', 'position' => 0]);

        $response = $this->get(route('listings.show', $listing))->assertOk();

        $response
            ->assertSee('<meta property="og:type" content="product">', false)
            // Escaped: a title cannot break out of the tag.
            ->assertSee('<meta property="og:title" content="Bàn gỗ &quot;sồi&quot; &lt;cũ&gt; - '.config('app.name').'">', false)
            ->assertSee('1.500.000 đ', false)
            ->assertSee('Bàn còn tốt. Giao tại nhà.', false)
            ->assertSee('<meta property="og:image" content="'.url(Storage::disk('public')->url('listings/1/cover.jpg')).'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false);

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $match);
        $data = json_decode($match[1] ?? '', true);

        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Bàn gỗ "sồi" <cũ>', $data['name']);
        $this->assertSame(1500000, $data['offers']['price']);
        $this->assertSame('VND', $data['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
        // "<" inside JSON-LD is encoded, so a title cannot close the script tag.
        $this->assertStringNotContainsString('<cũ>', $match[1]);
    }

    public function test_a_listing_without_a_stated_price_makes_no_offer(): void
    {
        $listing = Listing::factory()->fixedPrice()->create(['is_negotiable' => true, 'current_price' => 0]);

        $response = $this->get(route('listings.show', $listing))->assertOk()->assertSee('Giá thoả thuận', false);

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $match);
        $this->assertArrayNotHasKey('offers', json_decode($match[1], true));
    }

    public function test_private_and_unpublished_pages_are_kept_out_of_search_engines(): void
    {
        $seller = User::factory()->create();
        $pending = Listing::factory()->fixedPrice()->for($seller)->create(['moderation_status' => ModerationStatus::Pending]);

        $noindex = '<meta name="robots" content="noindex, nofollow">';

        $this->get(route('login'))->assertSee($noindex, false);
        $this->get(route('listings.index', ['q' => 'bàn']))->assertSee($noindex, false);
        $this->actingAs($seller)->get(route('dashboard'))->assertSee($noindex, false);
        $this->actingAs($seller)->get(route('listings.show', $pending))->assertOk()->assertSee($noindex, false);
    }

    public function test_the_sitemap_lists_what_is_public_and_nothing_else(): void
    {
        Cache::flush();

        $category = Category::factory()->create();
        $public = Listing::factory()->fixedPrice()->create();
        $hidden = Listing::factory()->fixedPrice()->create(['moderation_status' => ModerationStatus::Hidden]);
        $draft = Listing::factory()->fixedPrice()->create(['moderation_status' => ModerationStatus::Draft]);

        $response = $this->get(route('sitemap'))->assertOk();

        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $urls = [];

        foreach ($xml->url as $url) {
            $urls[] = (string) $url->loc;
        }

        $this->assertContains(route('home'), $urls);
        $this->assertContains(route('legal.terms'), $urls);
        $this->assertContains(route('categories.show', $category), $urls);
        $this->assertContains(route('listings.show', $public), $urls);
        $this->assertNotContains(route('listings.show', $hidden), $urls);
        $this->assertNotContains(route('listings.show', $draft), $urls);
    }

    public function test_robots_txt_points_to_the_sitemap_and_closes_private_areas(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $response->assertSee('Sitemap: '.route('sitemap'), false);
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /dashboard', false);
    }
}
