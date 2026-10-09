<?php

namespace App\Enums;

enum ItemCondition: string
{
    case Used = 'used';
    case LikeNew = 'like_new';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Used => 'Cũ',
            self::LikeNew => 'Như mới',
            self::Other => 'Khác',
        };
    }
}
