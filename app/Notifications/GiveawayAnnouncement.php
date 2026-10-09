<?php

namespace App\Notifications;

use App\Models\Giveaway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells every member that a new giveaway is open. Unlike the other
 * notifications it goes to everyone at once, so the emails are queued; the
 * in-app copy is still written straight away and shows on the bell at once.
 */
class GiveawayAnnouncement extends MarketplaceNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(Giveaway $giveaway)
    {
        parent::__construct('giveaway_new', 'Quà tặng mới: '.$giveaway->title, sprintf(
            'Quà: %s. Đăng ký tham gia miễn phí bằng mã số thành viên của bạn trước %s.',
            $giveaway->prize,
            $giveaway->ends_at->format('H:i d/m/Y'),
        ), route('giveaways.show', $giveaway, absolute: false));
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => (string) config('queue.default'),
        ];
    }
}
