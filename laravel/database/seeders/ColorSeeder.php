<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('colors')->insert([
            ['en_name'=>'Gold','vi_name'=>'Vàng'],
            ['en_name'=>'Silver','vi_name'=>'Bạc'],
            ['en_name'=>'Black','vi_name'=>'Đen'],
            ['en_name'=>'Rose Gold','vi_name'=>'Vàng Hồng'],
            ['en_name'=>'Red','vi_name'=>'Đỏ'],
            ['en_name'=>'Blue','vi_name'=>'Xanh Blue'],
            ['en_name'=>'Orange','vi_name'=>'Cam'],
            ['en_name'=>'Yellow','vi_name'=>'Vàng'],
            ['en_name'=>'Copper','vi_name'=>'Vàng Copper'],
            ['en_name'=>'Sierra Blue','vi_name'=>'Xanh Sierra'],
            ['en_name'=>'Mint','vi_name'=>'Xanh Mint'],
            ['en_name'=>'Pastel','vi_name'=>'Tím'],
            ['en_name'=>'Midnight','vi_name'=>'Đen Midnight'],
            ['en_name'=>'Alpine','vi_name'=>'Xanh'],
            ['en_name'=>'Natural Titanium','vi_name'=>'Titan Tự Nhiên'],
            ['en_name'=>'Blue Titanium','vi_name'=>'Titan Xanh'],
            ['en_name'=>'Black Titanium','vi_name'=>'Titan Đen'],
            ['en_name'=>'White Titanium','vi_name'=>'Titan Trắng'],
            ['en_name'=>'Titan Gold','vi_name'=>'Titan Sa Mạc'],
            ['en_name'=>'Space Gray','vi_name'=>'Đen Xám'],
            ['en_name'=>'Desert Titanium','vi_name'=>'Titan Sa Mạc'],
            ['en_name'=>'Starlight','vi_name'=>'Bạc Ánh Sao'],
            ['en_name'=>'Green','vi_name'=>'Xanh Green'],
            ['en_name'=>'Graphite','vi_name'=>'Xám'],
            ['en_name'=>'Pacific Blue','vi_name'=>'Xanh Đại Dương'],
            ['en_name'=>'Pink','vi_name'=>'Hồng'],
            ['en_name'=>'Alpine Green','vi_name'=>'Xanh Dương Đậm'],
            ['en_name'=>'Deep Purple','vi_name'=>'Tím Đậm'],
            ['en_name'=>'Purple','vi_name'=>'Tím'],
            ['en_name'=>'Ultramarine','vi_name'=>'Xanh Lưu Ly'],
            ['en_name'=>'Teal','vi_name'=>'Xanh Mòng Két'],
        ]);
    }
}
