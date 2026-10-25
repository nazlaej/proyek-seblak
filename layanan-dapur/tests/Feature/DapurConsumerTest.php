<?php

namespace Tests\Feature;

use App\Models\AntrianDapur;
use App\Console\Commands\DapurConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DapurConsumerTest extends TestCase
{
    use RefreshDatabase;

    private function eventPesanan(array $ubah = []): array
    {
        return array_merge([
            'event' => 'order.created',
            'data' => [
                'id' => 99,
                'nama_pelanggan' => 'Test Pelanggan',
                'nama_menu' => 'Ceker, Kerupuk',
                'jumlah' => 2,
                'harga' => '7000.00',
                'total_harga' => '7000.00',
                'metode_pembayaran' => 'tunai',
                'status' => 'diterima',
                'items' => [
                    ['menu_id' => 1, 'nama_menu' => 'Ceker', 'jumlah' => 1, 'harga_satuan' => '4000', 'subtotal' => '4000'],
                    ['menu_id' => 2, 'nama_menu' => 'Kerupuk', 'jumlah' => 1, 'harga_satuan' => '3000', 'subtotal' => '3000'],
                ]
            ],
            'occurred_at' => now()->toISOString(),
        ], $ubah);
    }

    public function test_terima_event_order_created(): void
    {
        $consumer = new DapurConsumer();
        $event = $this->eventPesanan();
        
        $result = $consumer->processEvent($event);
        
        $this->assertTrue($result);
        $this->assertDatabaseHas('antrian_dapur', [
            'order_id' => 99,
            'nama_pelanggan' => 'Test Pelanggan',
            'status' => 'menunggu',
        ]);
    }

    public function test_duplicate_event_tidak_double(): void
    {
        $consumer = new DapurConsumer();
        $event = $this->eventPesanan(['data' => ['id' => 100]]);
        
        $consumer->processEvent($event);
        $result = $consumer->processEvent($event); // kirim 2x
        
        $this->assertFalse($result);
        $this->assertEquals(1, AntrianDapur::where('order_id', 100)->count());
    }
}