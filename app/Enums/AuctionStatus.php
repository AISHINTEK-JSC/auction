<?php

namespace App\Enums;

/**
 * Mirrors App\Models\Enum\AuctionStatus of the auction engine.
 */
enum AuctionStatus: int
{
    case Upcoming = 0;
    case Running = 1;
    case Completed = 2;
    case CancelledBySeller = 3;
    case CancelledByBuyer = 4;
    case Expired = 5;

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Sắp diễn ra',
            self::Running => 'Đang diễn ra',
            self::Completed => 'Đã có người thắng',
            self::CancelledBySeller => 'Người bán đã huỷ',
            self::CancelledByBuyer => 'Người mua đã huỷ',
            self::Expired => 'Kết thúc, không có người thắng',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Upcoming || $this === self::Running;
    }

    /**
     * @return list<int>
     */
    public static function openValues(): array
    {
        return [self::Upcoming->value, self::Running->value];
    }
}
