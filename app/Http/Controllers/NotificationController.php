<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/Index', [
            'notifications' => $request->user()->notifications()
                ->paginate(20)
                ->through(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'kind' => $notification->data['kind'] ?? 'info',
                    'title' => $notification->data['title'] ?? '',
                    'body' => $notification->data['body'] ?? '',
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Mark one notification as read and go where it points.
     */
    public function show(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '/');

        // Stored URLs are our own relative paths; never follow anything else.
        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
