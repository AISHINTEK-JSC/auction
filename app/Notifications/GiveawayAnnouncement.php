<?php

namespace App\Notifications;

use App\Models\Giveaway;
use App\Support\EmailTemplate;
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
        $text = EmailTemplate::render('giveaway_new', [
            'chuong_trinh' => $giveaway->title,
            'qua' => $giveaway->prize,
            'han_dang_ky' => $giveaway->ends_at->format('H:i d/m/Y'),
        ]);

        parent::__construct('giveaway_new', $text['subject'], $text['body'], route('giveaways.show', $giveaway, absolute: false), 'giveaway_new');
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
