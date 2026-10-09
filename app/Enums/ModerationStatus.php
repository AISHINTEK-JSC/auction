<?php

namespace App\Enums;

enum ModerationStatus: string
{
    /** Saved by its seller to finish later: never shown, never sent anywhere. */
    case Draft = 'draft';

    /** Waiting for a moderator; its auction has not been opened in the engine yet. */
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    /** Taken down by a moderator after it was published. */
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Bị từ chối',
            self::Hidden => 'Đã ẩn',
        };
    }
}
