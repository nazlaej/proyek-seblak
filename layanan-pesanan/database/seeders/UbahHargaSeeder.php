<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class UbahHargaSeeder extends Seeder
{
    /**
     * Menyesuaikan harga menu Warung Seblak Ngakak.
     * Aman dijalankan berulang (updateOrCreate berdasarkan kolom `kode`).
     */
    public function run(): void
    {
        // [kode, nama_menu, kategori, harga, satuan]
        $daftar = [
            // AYAM
            ['TOP016', 'Ceker', 'ayam', 4000, 'buah'],
            ['TOP042', 'Tulang', 'ayam', 4000, 'buah'],

            // BAKSO
            ['TOP017', 'Bakso Daging', 'bakso', 3000, 'buah'],
            ['TOP023', 'Bakso Urat Jumbo', 'bakso', 6000, 'buah'],
            ['TOP039', 'Bakso Ikan', 'bakso', 3000, 'buah'],

            // KERUPUK (semua 3.000)
            ['TOP001', 'Kerupuk Putih', 'kerupuk', 3000, 'porsi'],
            ['TOP002', 'Kerupuk Bunga', 'kerupuk', 3000, 'porsi'],
            ['TOP003', 'Kerupuk Tangga', 'kerupuk', 3000, 'porsi'],
            ['TOP008', 'Kerupuk Rafael', 'kerupuk', 3000, 'porsi'],
            ['TOP009', 'Kerupuk Warna-warni', 'kerupuk', 3000, 'porsi'],

            // MI
            ['TOP005', 'Mie Dara', 'mi', 2000, 'porsi'],
            ['TOP027', 'Mie Telur', 'mi', 2000, 'porsi'],
            ['TOP028', 'Mie Instan', 'mi', 5000, 'porsi'],
            ['TOP029', 'Mie Glosor', 'mi', 2000, 'porsi'],
            ['TOP030', 'Kwetiau', 'mi', 3000, 'porsi'],

            // SAYURAN (semua 3.000)
            ['TOP013', 'Pakcoy', 'sayuran', 3000, 'porsi'],
            ['TOP014', 'Jamur Enoki', 'sayuran', 3000, 'porsi'],
            ['TOP019', 'Kol', 'sayuran', 3000, 'porsi'],
            ['TOP031', 'Sawi Hijau', 'sayuran', 3000, 'porsi'],
            ['TOP032', 'Tauge', 'sayuran', 3000, 'porsi'],
            ['TOP033', 'Wortel', 'sayuran', 3000, 'porsi'],
            ['TOP034', 'Daun Bawang', 'sayuran', 3000, 'porsi'],
            ['TOP035', 'Jagung Manis', 'sayuran', 3000, 'porsi'],

            // SUKI
            ['TOP011', 'Chikuwa', 'suki', 4000, 'buah'],
            ['TOP012', 'Dumpling Keju', 'suki', 4000, 'buah'],
            ['TOP015', 'Dumpling Ayam', 'suki', 4000, 'buah'],
            ['TOP024', 'Enoki Beef', 'suki', 5000, 'buah'],
            ['TOP025', 'Odeng', 'suki', 5000, 'buah'],
            ['TOP040', 'Crab Stick', 'suki', 4000, 'buah'],
            ['TOP041', 'Fish Roll', 'suki', 3000, 'buah'],

            // SOSIS
            ['TOP018', 'Sosis Merah', 'sosis', 3000, 'buah'],
            ['TOP021', 'Sosis', 'sosis', 3000, 'buah'],
            ['TOP026', 'Sosis Jumbo', 'sosis', 5000, 'buah'],

            // TELUR
            ['TOP020', 'Telur Puyuh', 'telur', 4000, 'buah'],
            ['TOP036', 'Telur Ayam', 'telur', 5000, 'buah'],

            // PELENGKAP
            ['TOP004', 'Siomay Kering', 'pelengkap', 2500, 'buah'],
            ['TOP006', 'Makaroni', 'pelengkap', 2500, 'porsi'],
            ['TOP007', 'Pilus Cikur', 'pelengkap', 2500, 'porsi'],
            ['TOP010', 'Cuanki Lidah', 'pelengkap', 2500, 'buah'],
            ['TOP022', 'Tahu Bakso', 'pelengkap', 3000, 'buah'],
            ['TOP037', 'Cilok', 'pelengkap', 2000, 'buah'],
            ['TOP038', 'Batagor Kering', 'pelengkap', 3000, 'porsi'],
        ];

        // MINUMAN (semua 8.000)
        $minuman = [
            'MIN001' => 'Air Mineral',
            'MIN002' => 'Es Teh Manis',
            'MIN003' => 'Teh Tawar',
            'MIN004' => 'Es Jeruk',
            'MIN005' => 'Lemon Tea',
            'MIN006' => 'Nutrisari Es',
            'MIN007' => 'Pop Ice',
            'MIN008' => 'Cappuccino Cincau',
            'MIN009' => 'Milo Es',
            'MIN010' => 'Susu Cokelat',
            'MIN011' => 'Matcha Latte',
            'MIN012' => 'Thai Tea',
            'MIN013' => 'Es Kopi Susu',
            'MIN014' => 'Teh Tarik',
            'MIN015' => 'Es Cokelat',
        ];

        foreach ($daftar as [$kode, $nama, $kategori, $harga, $satuan]) {
            Menu::updateOrCreate(
                ['kode' => $kode],
                ['nama_menu' => $nama, 'kategori' => $kategori, 'harga' => $harga, 'satuan' => $satuan],
            );
        }

        foreach ($minuman as $kode => $nama) {
            Menu::updateOrCreate(
                ['kode' => $kode],
                ['nama_menu' => $nama, 'kategori' => 'minuman', 'harga' => 8000, 'satuan' => 'gelas'],
            );
        }

        // Menu baru "Tulang" dibuat dengan stok awal 20 (menu lain tidak diubah stoknya).
        Menu::where('kode', 'TOP042')->update(['stok' => 20]);
    }
}
