<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Giveaway;
use App\Models\Listing;
use App\Models\Tag;
use App\Support\MarketplaceSettings;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Pages and files about the site itself: terms, privacy, sitemap, robots.txt.
 */
class SiteController extends Controller
{
    private const SITEMAP_LISTINGS = 5000;

    public function terms(): InertiaResponse
    {
        return Inertia::render('legal/Terms', $this->legalProps())
            ->withViewData('seo', Seo::page('Điều khoản sử dụng', 'Quy định khi đăng tin, đấu giá, mua bán và tham gia quà tặng trên '.config('app.name').'.'));
    }

    public function privacy(): InertiaResponse
    {
        return Inertia::render('legal/Privacy', $this->legalProps())
            ->withViewData('seo', Seo::page('Chính sách quyền riêng tư', config('app.name').' thu thập những thông tin nào, dùng vào việc gì và ai được thấy.'));
    }

    /**
     * Every public page, for search engines. Rebuilt at most every ten minutes.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(10), function (): string {
            $urls = [
                [route('home'), null],
                [route('listings.index'), null],
                [route('giveaways.index'), null],
                [route('legal.terms'), null],
                [route('legal.privacy'), null],
            ];

            foreach (Category::query()->orderBy('id')->get() as $category) {
                $urls[] = [route('categories.show', $category), $category->updated_at];
            }

            foreach (Tag::query()->whereHas('listings', fn ($query) => $query->published())->orderBy('id')->get() as $tag) {
                $urls[] = [route('tags.show', $tag), $tag->updated_at];
            }

            foreach (Giveaway::query()->latest('id')->limit(200)->get() as $giveaway) {
                $urls[] = [route('giveaways.show', $giveaway), $giveaway->updated_at];
            }

            $listings = Listing::query()
                ->published()
                ->latest('id')
                ->limit(self::SITEMAP_LISTINGS)
                ->get(['id', 'updated_at', 'moderation_status', 'sale_type', 'auction_id']);

            foreach ($listings as $listing) {
                $urls[] = [route('listings.show', $listing), $listing->updated_at];
            }

            $entries = array_map(fn (array $url) => sprintf(
                "  <url>\n    <loc>%s</loc>%s\n  </url>",
                htmlspecialchars($url[0], ENT_XML1),
                $url[1] ? "\n    <lastmod>".$url[1]->toAtomString().'</lastmod>' : '',
            ), $urls);

            return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n".implode("\n", $entries)."\n</urlset>\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Keeps crawlers out of the private areas and points them at the sitemap.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /settings',
            'Disallow: /notifications',
            'Disallow: /saved-searches',
            'Disallow: /listings/create',
            'Disallow: /login',
            'Disallow: /register',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return array{contactEmail: string, updatedAt: string}
     */
    private function legalProps(): array
    {
        return [
            'contactEmail' => MarketplaceSettings::contactEmail(),
            // The date the texts in resources/js/pages/legal were last revised.
            'updatedAt' => '10/10/2026',
        ];
    }
}
