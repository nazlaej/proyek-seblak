<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Services\OrderPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesananApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pesanan_berhasil_dibuat(): void
    {
        // Publisher hanya dimock pada pengujian ini.
        $this->mock(OrderPublisher::class, function ($mock) {
            $mock->shouldReceive('publish')
                ->once();
        });

        // Membuat menu untuk pengujian.
        Menu::create([
            'kode' => 'TEST001',
            'nama_menu' => 'Menu Pengujian',
            'kategori' => 'Makanan',
            'harga' => 5000,
            'satuan' => 'item',
            'tersedia' => true,
            'stok' => 10,
        ]);

        // Mengirim permintaan pembuatan pesanan.
        $response = $this->postJson('/api/v1/pesanan', [
            'nama_pelanggan' => 'Pelanggan Test',
            'metode_pembayaran' => 'QRIS',
            'items' => [
                [
                    'kode_menu' => 'TEST001',
                    'jumlah' => 2,
                ],
            ],
        ]);

        // Memastikan API berhasil membuat pesanan.
        $response->assertStatus(201);

        // Memastikan pesanan tersimpan.
        $this->assertDatabaseHas('orders', [
            'nama_pelanggan' => 'Pelanggan Test',
            'metode_pembayaran' => 'QRIS',
        ]);

        // Memastikan detail pesanan tersimpan.
        $this->assertDatabaseHas('order_items', [
            'nama_menu' => 'Menu Pengujian',
            'jumlah' => 2,
        ]);
    }

    public function test_pesanan_gagal_jika_nama_pelanggan_kosong(): void
    {
        // Mengirim permintaan tanpa nama pelanggan.
        $response = $this->postJson('/api/v1/pesanan', [
            'metode_pembayaran' => 'QRIS',
            'items' => [
                [
                    'kode_menu' => 'TEST001',
                    'jumlah' => 2,
                ],
            ],
        ]);

        // Memastikan API menolak data yang tidak valid.
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'nama_pelanggan',
        ]);
    }

    public function test_stok_berkurang_saat_pesanan_dibuat(): void
    {
        $this->mock(OrderPublisher::class, function ($mock) {
            $mock->shouldReceive('publish')->once();
        });

        $menu = Menu::create([
            'kode' => 'TEST002',
            'nama_menu' => 'Menu Stok',
            'kategori' => 'Makanan',
            'harga' => 3000,
            'satuan' => 'item',
            'tersedia' => true,
            'stok' => 10,
        ]);

        $this->postJson('/api/v1/pesanan', [
            'nama_pelanggan' => 'Pelanggan Stok',
            'items' => [
                ['kode_menu' => 'TEST002', 'jumlah' => 3],
            ],
        ])->assertStatus(201);

        // Stok awal 10, dipesan 3, harus tersisa 7.
        $this->assertDatabaseHas('menus', [
            'kode' => 'TEST002',
            'stok' => 7,
        ]);
    }

    public function test_pesanan_ditolak_jika_stok_tidak_cukup(): void
    {
        $this->mock(OrderPublisher::class, function ($mock) {
            $mock->shouldReceive('publish')->never();
        });

        Menu::create([
            'kode' => 'TEST003',
            'nama_menu' => 'Menu Sedikit',
            'kategori' => 'Makanan',
            'harga' => 3000,
            'satuan' => 'item',
            'tersedia' => true,
            'stok' => 2,
        ]);

        $response = $this->postJson('/api/v1/pesanan', [
            'nama_pelanggan' => 'Pelanggan Rakus',
            'items' => [
                ['kode_menu' => 'TEST003', 'jumlah' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);

        // Pesanan tidak boleh tersimpan, dan stok tidak boleh berubah.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('menus', [
            'kode' => 'TEST003',
            'stok' => 2,
        ]);
    }
}