<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

class NotificationPresenter
{
    /**
     * @return array{id: string, kind: string, title: string, body: string, read: bool, created_at: string|null}
     */
    public static function item(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => (string) ($notification->data['kind'] ?? 'info'),
            'title' => (string) ($notification->data['title'] ?? ''),
            'body' => (string) ($notification->data['body'] ?? ''),
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
