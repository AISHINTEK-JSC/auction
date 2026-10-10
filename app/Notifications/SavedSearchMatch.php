<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\SavedSearch;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A new listing matches a search the member saved. Shown in the app at once;
 * the email is queued, since one listing can match many members.
 */
class SavedSearchMatch extends MarketplaceNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(Listing $listing, SavedSearch $search)
    {
        $text = EmailTemplate::render('saved_search', [
            'tin' => $listing->title,
            'tim_kiem' => $search->label(),
        ]);

        parent::__construct('saved_search', $text['subject'], $text['body'], route('listings.show', $listing, absolute: false), 'saved_search');
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
