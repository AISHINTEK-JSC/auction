<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\City;
use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    private const RECENT_NOTIFICATIONS = 8;

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Header menu and filters. Closures: skipped by partial reloads that do not ask for them.
            'categories' => fn () => Category::tree(),
            'cities' => fn () => City::options(),
            'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            // For the dropdown under the bell. Optional: only sent when the
            // dropdown asks for it, so ordinary page loads do not pay for it.
            'recentNotifications' => Inertia::optional(fn () => array_values(
                $request->user()?->notifications()
                    ->limit(self::RECENT_NOTIFICATIONS)
                    ->get()
                    ->map(fn (DatabaseNotification $notification) => NotificationPresenter::item($notification))
                    ->all() ?? [],
            )),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
