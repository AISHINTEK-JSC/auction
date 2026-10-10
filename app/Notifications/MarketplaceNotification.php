<?php

namespace App\Notifications;

use App\Models\Giveaway;
use App\Models\Listing;
use App\Models\User;
use App\Support\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification type for everything the marketplace tells a member. It is
 * stored for the bell in the header and also sent by email. What each one
 * says comes from App\Support\EmailTemplate, where an admin can reword it.
 */
class MarketplaceNotification extends Notification
{
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        /** The EmailTemplate type this was written from; decides the button's label. */
        public readonly ?string $template = null,
    ) {}

    public static function outbid(Listing $listing): self
    {
        return self::of('outbid', ['gia' => self::money($listing->current_price), 'tin' => $listing->title], self::listingUrl($listing));
    }

    public static function won(Listing $listing): self
    {
        return self::of('won', ['tin' => $listing->title, 'gia' => self::money($listing->winning_price)], self::listingUrl($listing));
    }

    public static function lost(Listing $listing): self
    {
        return self::of('lost', ['tin' => $listing->title], self::listingUrl($listing));
    }

    public static function sold(Listing $listing, ?User $winner): self
    {
        return self::of('sold', [
            'tin' => $listing->title,
            'gia' => self::money($listing->winning_price),
            'nguoi_thang' => $winner->name ?? 'người thắng',
        ], self::listingUrl($listing));
    }

    public static function unsold(Listing $listing): self
    {
        return self::of('unsold', ['tin' => $listing->title], route('dashboard', absolute: false));
    }

    public static function cancelled(Listing $listing): self
    {
        return self::of('cancelled', ['tin' => $listing->title], self::listingUrl($listing));
    }

    public static function winnerWithdrew(Listing $listing, bool $hasSuccessor): self
    {
        // Two texts, one kind: the bell shows both the same way.
        return self::of(
            $hasSuccessor ? 'winner_withdrew' : 'winner_withdrew_final',
            ['tin' => $listing->title],
            self::listingUrl($listing),
            kind: 'winner_withdrew',
        );
    }

    public static function expiringSoon(Listing $listing): self
    {
        return self::of('expiring', [
            'tin' => $listing->title,
            'het_han' => $listing->ends_at?->format('H:i d/m/Y'),
        ], self::listingUrl($listing));
    }

    public static function expired(Listing $listing): self
    {
        return self::of('expired', ['tin' => $listing->title], route('dashboard', absolute: false));
    }

    public static function forfeitWarning(Listing $listing, ?int $nextBanDays): self
    {
        return self::of('forfeit_warning', [
            'tin' => $listing->title,
            'canh_bao' => $nextBanDays ? "Nếu từ bỏ thêm lần nữa, bạn sẽ bị tạm khoá đặt giá {$nextBanDays} ngày." : '',
        ], self::listingUrl($listing));
    }

    public static function bidBan(Listing $listing, \DateTimeInterface $until, int $count): self
    {
        return self::of('bid_ban', [
            'so_lan' => $count,
            'tin' => $listing->title,
            'den' => $until->format('H:i d/m/Y'),
        ], self::listingUrl($listing));
    }

    public static function moderated(Listing $listing): self
    {
        return self::of('moderation', [
            'tin' => $listing->title,
            'trang_thai' => mb_strtolower($listing->moderation_status->label()),
            'ghi_chu' => $listing->moderation_note ? 'Ghi chú của kiểm duyệt viên: '.$listing->moderation_note : '',
        ], $listing->isPublished() ? self::listingUrl($listing) : route('dashboard', absolute: false));
    }

    public static function giveawayWon(Giveaway $giveaway): self
    {
        return self::of('giveaway_won', [
            'chuong_trinh' => $giveaway->title,
            'qua' => $giveaway->prize,
        ], route('giveaways.show', $giveaway, absolute: false), kind: 'giveaway');
    }

    /**
     * A notification written from one of the editable templates (admin panel >
     * Mẫu email): its subject and body with the {placeholders} filled in.
     *
     * @param  string  $template  a key of EmailTemplate::TYPES
     * @param  array<string, string|int|null>  $variables
     * @param  string|null  $kind  what the bell files it under; the template key unless given
     */
    public static function of(string $template, array $variables, string $url, ?string $kind = null): self
    {
        $text = EmailTemplate::render($template, $variables);

        return new self($kind ?? $template, $text['subject'], $text['body'], $url, $template);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::withBody(
            EmailTemplate::message($notifiable->name ?? null)->subject($this->title),
            $this->body,
            $this->template,
            url($this->url),
        );
    }

    /**
     * @return array{kind: string, title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    private static function listingUrl(Listing $listing): string
    {
        return route('listings.show', $listing, absolute: false);
    }

    private static function money(?int $amount): string
    {
        return number_format((int) $amount, 0, ',', '.').' đ';
    }
}
