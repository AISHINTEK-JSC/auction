<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    private const TREE = [
        'Điện tử' => ['Điện thoại', 'Máy tính, laptop', 'Máy ảnh', 'Âm thanh'],
        'Xe cộ' => ['Xe máy', 'Ô tô', 'Xe đạp'],
        'Thời trang' => ['Quần áo', 'Giày dép', 'Đồng hồ', 'Túi xách'],
        'Nhà cửa' => ['Nội thất', 'Đồ gia dụng'],
        'Sưu tầm' => ['Tiền xưa', 'Tem', 'Đồ cổ'],
        'Khác' => [],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::TREE as $name => $children) {
            $parent = Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'position' => $position++],
            );

            foreach ($children as $index => $child) {
                Category::query()->firstOrCreate(
                    ['slug' => Str::slug($child)],
                    ['name' => $child, 'parent_id' => $parent->id, 'position' => $index],
                );
            }
        }
    }
}
