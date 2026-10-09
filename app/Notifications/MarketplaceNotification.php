<?php

namespace App\Notifications;

use App\Models\Giveaway;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification type for everything the marketplace tells a member. It is
 * stored for the bell in the header and also sent by email.
 */
class MarketplaceNotification extends Notification
{
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
    ) {}

    public static function outbid(Listing $listing): self
    {
        return new self('outbid', 'Bạn đã bị vượt giá', sprintf(
            'Có người vừa đặt %s cho "%s". Hãy đặt giá cao hơn nếu bạn vẫn muốn thắng.',
            self::money($listing->current_price),
            $listing->title,
        ), self::listingUrl($listing));
    }

    public static function won(Listing $listing): self
    {
        return new self('won', 'Bạn đã thắng phiên đấu giá', sprintf(
            'Bạn thắng "%s" với giá %s. Mở trang phiên để xem thông tin liên hệ của người bán.',
            $listing->title,
            self::money($listing->winning_price),
        ), self::listingUrl($listing));
    }

    public static function lost(Listing $listing): self
    {
        return new self('lost', 'Phiên đấu giá đã kết thúc', sprintf(
            'Phiên "%s" đã kết thúc và bạn không phải là người thắng.',
            $listing->title,
        ), self::listingUrl($listing));
    }

    public static function sold(Listing $listing, ?User $winner): self
    {
        return new self('sold', 'Phiên của bạn đã có người thắng', sprintf(
            '"%s" được chốt ở giá %s%s. Mở trang phiên để xem thông tin liên hệ của người thắng.',
            $listing->title,
            self::money($listing->winning_price),
            $winner ? ' cho '.$winner->name : '',
        ), self::listingUrl($listing));
    }

    public static function unsold(Listing $listing): self
    {
        return new self('unsold', 'Phiên của bạn kết thúc không có người thắng', sprintf(
            '"%s" đã kết thúc mà không có lượt đặt nào đạt yêu cầu. Bạn có thể đăng lại từ trang phiên đấu giá của tôi.',
            $listing->title,
        ), route('dashboard', absolute: false));
    }

    public static function cancelled(Listing $listing): self
    {
        return new self('cancelled', 'Phiên đấu giá đã bị huỷ', sprintf(
            'Phiên "%s" mà bạn tham gia đã bị huỷ.',
            $listing->title,
        ), self::listingUrl($listing));
    }

    public static function winnerWithdrew(Listing $listing, bool $hasSuccessor): self
    {
        return new self('winner_withdrew', 'Người thắng đã từ bỏ kết quả', sprintf(
            $hasSuccessor
                ? 'Người thắng "%s" đã từ bỏ. Người đặt giá cao kế tiếp trở thành người thắng.'
                : 'Người thắng "%s" đã từ bỏ và không còn ai kế tiếp. Phiên đã bị huỷ, bạn có thể đăng lại.',
            $listing->title,
        ), self::listingUrl($listing));
    }

    public static function expiringSoon(Listing $listing): self
    {
        return new self('expiring', 'Tin đăng của bạn sắp hết hạn', sprintf(
            'Tin "%s" sẽ hết hạn lúc %s. Bấm "Gia hạn" ở trang tin để tiếp tục hiển thị.',
            $listing->title,
            $listing->ends_at?->format('H:i d/m/Y'),
        ), self::listingUrl($listing));
    }

    public static function expired(Listing $listing): self
    {
        return new self('expired', 'Tin đăng của bạn đã hết hạn', sprintf(
            'Tin "%s" đã hết thời gian hiển thị. Bạn có thể gia hạn hoặc đăng lại từ trang tin đăng của tôi.',
            $listing->title,
        ), route('dashboard', absolute: false));
    }

    public static function forfeitWarning(Listing $listing, ?int $nextBanDays): self
    {
        return new self('forfeit_warning', 'Bạn đã từ bỏ một kết quả đấu giá', sprintf(
            'Kết quả phiên "%s" đã được chuyển cho người khác.%s',
            $listing->title,
            $nextBanDays ? " Nếu từ bỏ thêm lần nữa, bạn sẽ bị tạm khoá đặt giá {$nextBanDays} ngày." : '',
        ), self::listingUrl($listing));
    }

    public static function bidBan(Listing $listing, \DateTimeInterface $until, int $count): self
    {
        return new self('bid_ban', 'Bạn bị tạm khoá đặt giá', sprintf(
            'Bạn đã từ bỏ kết quả đấu giá %d lần gần đây (lần này: "%s"). Bạn không thể đặt giá cho tới %s.',
            $count,
            $listing->title,
            $until->format('H:i d/m/Y'),
        ), self::listingUrl($listing));
    }

    public static function moderated(Listing $listing): self
    {
        $status = $listing->moderation_status;

        return new self('moderation', 'Tin đăng của bạn: '.mb_strtolower($status->label()), sprintf(
            'Tin "%s" %s.%s',
            $listing->title,
            mb_strtolower($status->label()),
            $listing->moderation_note ? ' Ghi chú của kiểm duyệt viên: '.$listing->moderation_note : '',
        ), $listing->isPublished() ? self::listingUrl($listing) : route('dashboard', absolute: false));
    }

    public static function giveawayWon(Giveaway $giveaway): self
    {
        return new self('giveaway', 'Bạn đã trúng quà tặng', sprintf(
            'Mã số của bạn đã được chọn trong chương trình "%s". Quà: %s.',
            $giveaway->title,
            $giveaway->prize,
        ), route('giveaways.show', $giveaway, absolute: false));
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
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Xin chào '.($notifiable->name ?? 'bạn').',')
            ->line($this->body)
            ->action('Xem chi tiết', url($this->url));
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
