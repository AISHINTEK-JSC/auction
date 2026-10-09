<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Provinces and centrally-run cities of Vietnam. The big three are listed first in the UI.
     */
    private const FEATURED = ['Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng'];

    private const OTHERS = [
        'An Giang', 'Bà Rịa - Vũng Tàu', 'Bắc Giang', 'Bắc Kạn', 'Bạc Liêu', 'Bắc Ninh', 'Bến Tre', 'Bình Định',
        'Bình Dương', 'Bình Phước', 'Bình Thuận', 'Cà Mau', 'Cần Thơ', 'Cao Bằng', 'Đắk Lắk', 'Đắk Nông',
        'Điện Biên', 'Đồng Nai', 'Đồng Tháp', 'Gia Lai', 'Hà Giang', 'Hà Nam', 'Hà Tĩnh', 'Hải Dương',
        'Hải Phòng', 'Hậu Giang', 'Hoà Bình', 'Hưng Yên', 'Khánh Hoà', 'Kiên Giang', 'Kon Tum', 'Lai Châu',
        'Lâm Đồng', 'Lạng Sơn', 'Lào Cai', 'Long An', 'Nam Định', 'Nghệ An', 'Ninh Bình', 'Ninh Thuận',
        'Phú Thọ', 'Phú Yên', 'Quảng Bình', 'Quảng Nam', 'Quảng Ngãi', 'Quảng Ninh', 'Quảng Trị', 'Sóc Trăng',
        'Sơn La', 'Tây Ninh', 'Thái Bình', 'Thái Nguyên', 'Thanh Hoá', 'Thừa Thiên Huế', 'Tiền Giang',
        'Trà Vinh', 'Tuyên Quang', 'Vĩnh Long', 'Vĩnh Phúc', 'Yên Bái',
    ];

    public function run(): void
    {
        foreach (self::FEATURED as $name) {
            City::query()->firstOrCreate(['name' => $name], ['priority' => 1]);
        }

        foreach (self::OTHERS as $name) {
            City::query()->firstOrCreate(['name' => $name], ['priority' => 0]);
        }
    }
}
