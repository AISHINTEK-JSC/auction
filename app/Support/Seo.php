<?php

namespace App\Support;

use App\Enums\AuctionStatus;
use App\Models\Giveaway;
use App\Models\Listing;
use Illuminate\Support\Str;

/**
 * What the page says about itself to search engines and to apps that preview
 * a shared link (Facebook, Zalo, Messenger…). Those read the HTML the server
 * sends and do not run the page's JavaScript, so it is rendered in
 * resources/views/app.blade.php rather than in the Vue pages.
 *
 * A controller attaches it with ->withViewData('seo', Seo::...). A page that
 * attaches nothing is treated as private and marked "noindex".
 */
class Seo
{
    private const DESCRIPTION_LENGTH = 160;

    /** Shown when a shared page has no picture of its own: the logo and name, 1200×630. */
    private const SHARE_IMAGE = 'og-image.png';

    /**
     * A public page.
     *
     * @return array<string, mixed>
     */
    public static function page(string $title, string $description, ?string $image = null, bool $indexable = true): array
    {
        return [
            'title' => $title.' - '.config('app.name'),
            'description' => Str::limit(self::plain($description), self::DESCRIPTION_LENGTH),
            'image' => $image,
            'type' => 'website',
            'indexable' => $indexable,
            'json_ld' => null,
        ];
    }

    /**
     * The home page: the site name leads.
     *
     * @return array<string, mixed>
     */
    public static function home(): array
    {
        return [
            ...self::page('', self::siteDescription()),
            'title' => config('app.name').' - Đấu giá và mua bán trực tuyến',
        ];
    }

    /**
     * A listing: its own title, a summary with the price, its cover image, and
     * a schema.org Product so search results can show the price.
     *
     * @return array<string, mixed>
     */
    public static function listing(Listing $listing): array
    {
        $price = $listing->is_negotiable
            ? 'Giá thoả thuận'
            : number_format($listing->winning_price ?? $listing->current_price, 0, ',', '.').' đ';
        $summary = self::plain((string) $listing->description);
        $lead = sprintf('%s · %s · %s%s.', $listing->auction_type?->label() ?? $listing->sale_type->label(), $price, $listing->statusLabel(), $listing->city ? ' · '.$listing->city->name : '');
        $image = $listing->images->first()?->url;
        $forSale = $listing->auction_status->isOpen();

        return [
            ...self::page($listing->title, trim($lead.' '.$summary), $image, $listing->isPublished()),
            'type' => 'product',
            'json_ld' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $listing->title,
                'description' => Str::limit($summary !== '' ? $summary : $lead, 500),
                'image' => $listing->images->map(fn ($image) => $image->url)->all() ?: null,
                'category' => $listing->category?->name,
                'brand' => $listing->brand === null ? null : ['@type' => 'Brand', 'name' => $listing->brand],
                // No offer without a stated price ("giá thoả thuận").
                'offers' => $listing->is_negotiable ? null : [
                    '@type' => 'Offer',
                    'price' => $listing->winning_price ?? $listing->current_price,
                    'priceCurrency' => 'VND',
                    'availability' => $forSale ? 'https://schema.org/InStock' : ($listing->auction_status === AuctionStatus::Completed ? 'https://schema.org/SoldOut' : 'https://schema.org/Discontinued'),
                    'url' => route('listings.show', $listing),
                ],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function giveaway(Giveaway $giveaway): array
    {
        return self::page(
            $giveaway->title,
            'Quà tặng: '.$giveaway->prize.'. '.($giveaway->description ?? 'Đăng ký tham gia miễn phí bằng mã số thành viên.'),
            $giveaway->image,
        );
    }

    /**
     * What the template renders: the page's own data, or the private default.
     *
     * @param  array<string, mixed>|null  $seo
     * @return array<string, mixed>
     */
    public static function resolve(?array $seo): array
    {
        $seo ??= [
            'title' => config('app.name'),
            'description' => self::siteDescription(),
            'image' => null,
            'type' => 'website',
            // Dashboards, forms, settings: nothing a search engine should list.
            'indexable' => false,
            'json_ld' => null,
        ];

        return [
            ...$seo,
            // Link previews need a full address, not a path.
            'image' => url($seo['image'] ?? self::SHARE_IMAGE),
            // Without the query string: filters and pages are not separate documents.
            'url' => url()->current(),
        ];
    }

    public static function siteDescription(): string
    {
        return 'Sàn đấu giá và mua bán trực tuyến: đấu giá xuôi, đấu giá ngược và tin giá cố định, cập nhật theo từng lượt đặt giá.';
    }

    /**
     * Rich text reduced to one line of plain text.
     */
    private static function plain(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5)));
    }
}
