<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class StokMenuSeeder extends Seeder
{
    /**
     * Mengisi stok awal semua menu.
     * Pelanggan hanya bisa memilih menu yang stoknya masih ada.
     */
    public function run(): void
    {
        Menu::query()->update(['stok' => 20]);
    }
}
