<?php

namespace App\Enums;

enum ReportReason: string
{
    case Counterfeit = 'counterfeit';
    case Prohibited = 'prohibited';
    case Scam = 'scam';
    case WrongCategory = 'wrong_category';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Counterfeit => 'Hàng giả, hàng nhái',
            self::Prohibited => 'Hàng cấm',
            self::Scam => 'Có dấu hiệu lừa đảo',
            self::WrongCategory => 'Sai danh mục',
            self::Other => 'Lý do khác',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $reason) => ['value' => $reason->value, 'label' => $reason->label()], self::cases());
    }
}
