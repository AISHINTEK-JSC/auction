<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';

    /** Reviews listings, reports and comments in the admin panel. */
    case Moderator = 'moderator';

    /** Everything a moderator can do, plus users, catalog, settings and giveaways. */
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Thành viên',
            self::Moderator => 'Kiểm duyệt viên',
            self::Admin => 'Quản trị viên',
        };
    }
}
