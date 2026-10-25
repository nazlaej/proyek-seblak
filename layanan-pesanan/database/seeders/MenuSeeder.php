<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            // TOPPING DARI GAMBAR - Rp1.000
            ['Kerupuk Putih', 'kerupuk', 1000, 'porsi'],
            ['Kerupuk Bunga', 'kerupuk', 1000, 'porsi'],
            ['Kerupuk Tangga', 'kerupuk', 1000, 'porsi'],
            ['Siomay Kering', 'pelengkap', 1000, 'porsi'],
            ['Mie Dara', 'mi', 1000, 'porsi'],
            ['Makaroni', 'pelengkap', 1000, 'porsi'],
            ['Pilus Cikur', 'pelengkap', 1000, 'porsi'],
            ['Kerupuk Rafael', 'kerupuk', 1000, 'porsi'],
            ['Kerupuk Warna-warni', 'kerupuk', 1000, 'porsi'],
            ['Cuanki Lidah', 'pelengkap', 1000, 'buah'],

            // TOPPING DARI GAMBAR - Rp2.000
            ['Chikuwa', 'suki', 2000, 'buah'],
            ['Dumpling Keju', 'suki', 2000, 'buah'],
            ['Pakcoy', 'sayuran', 2000, 'porsi'],
            ['Jamur Enoki', 'sayuran', 2000, 'porsi'],
            ['Dumpling Ayam', 'suki', 2000, 'buah'],
            ['Ceker', 'ayam', 2000, 'buah'],
            ['Bakso Daging', 'bakso', 2000, 'buah'],
            ['Sosis Merah', 'sosis', 2000, 'buah'],
            ['Kol', 'sayuran', 2000, 'porsi'],
            ['Telur Puyuh', 'telur', 2000, 'buah'],
            ['Sosis', 'sosis', 2000, 'buah'],
            ['Tahu Bakso', 'pelengkap', 2000, 'buah'],

            // TOPPING DARI GAMBAR - Rp5.000
            ['Bakso Urat Jumbo', 'bakso', 5000, 'buah'],
            ['Enoki Beef', 'suki', 5000, 'buah'],
            ['Odeng', 'suki', 5000, 'buah'],
            ['Sosis Jumbo', 'sosis', 5000, 'buah'],

            // TAMBAHAN MI - HARGA ESTIMASI
            ['Mie Telur', 'mi', 2000, 'porsi'],
            ['Mie Instan', 'mi', 3000, 'porsi'],
            ['Mie Glosor', 'mi', 2000, 'porsi'],
            ['Kwetiau', 'mi', 3000, 'porsi'],

            // TAMBAHAN SAYURAN - HARGA ESTIMASI
            ['Sawi Hijau', 'sayuran', 1000, 'porsi'],
            ['Tauge', 'sayuran', 1000, 'porsi'],
            ['Wortel', 'sayuran', 1000, 'porsi'],
            ['Daun Bawang', 'sayuran', 1000, 'porsi'],
            ['Jagung Manis', 'sayuran', 2000, 'porsi'],

            // TAMBAHAN TELUR - HARGA ESTIMASI
            ['Telur Ayam', 'telur', 3000, 'butir'],

            // TAMBAHAN TOPPING - HARGA ESTIMASI
            ['Cilok', 'pelengkap', 1000, 'buah'],
            ['Batagor Kering', 'pelengkap', 1000, 'buah'],
            ['Bakso Ikan', 'bakso', 2000, 'buah'],
            ['Crab Stick', 'suki', 2500, 'buah'],
            ['Fish Roll', 'suki', 2500, 'buah'],

            // MINUMAN - HARGA ESTIMASI
            ['Air Mineral', 'minuman', 3000, 'botol'],
            ['Es Teh Manis', 'minuman', 4000, 'gelas'],
            ['Teh Tawar', 'minuman', 2000, 'gelas'],
            ['Es Jeruk', 'minuman', 5000, 'gelas'],
            ['Lemon Tea', 'minuman', 5000, 'gelas'],
            ['Nutrisari Es', 'minuman', 4000, 'gelas'],
            ['Pop Ice', 'minuman', 5000, 'gelas'],
            ['Cappuccino Cincau', 'minuman', 6000, 'gelas'],
            ['Milo Es', 'minuman', 6000, 'gelas'],
            ['Susu Cokelat', 'minuman', 5000, 'gelas'],
            ['Matcha Latte', 'minuman', 8000, 'gelas'],
            ['Thai Tea', 'minuman', 7000, 'gelas'],
            ['Es Kopi Susu', 'minuman', 8000, 'gelas'],
            ['Teh Tarik', 'minuman', 6000, 'gelas'],
            ['Es Cokelat', 'minuman', 7000, 'gelas'],
        ];

        $now = now();
        $data = [];

      foreach ($menus as $index => $menu) {
    if ($menu[1] === 'minuman') {
        $prefix = 'MIN';
    } elseif (in_array($menu[1], ['kerupuk', 'pelengkap', 'suki', 'sayuran', 'ayam', 'bakso', 'sosis', 'telur', 'mi'])) {
        $prefix = 'TOP';
    } else {
        $prefix = 'MEN';
    }

    $nomor = match ($prefix) {
        'MIN' => count(array_filter(
            array_slice($menus, 0, $index + 1),
            fn ($item) => $item[1] === 'minuman'
        )),
        'TOP' => count(array_filter(
            array_slice($menus, 0, $index + 1),
            fn ($item) => $item[1] !== 'minuman'
        )),
        default => $index + 1,
    };

    $data[] = [
        'kode' => $prefix . str_pad((string) $nomor, 3, '0', STR_PAD_LEFT),
        'nama_menu' => $menu[0],
        'kategori' => $menu[1],
        'harga' => $menu[2],
        'satuan' => $menu[3],
        'tersedia' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}
        DB::table('menus')->insert($data);
    }
}