<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Services\OrderPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesananApiTest extends TestCase
{
    use RefreshDatabase;

    private function menu(string $kode, int $stok = 10): Menu
    {
        return Menu::create([
            'kode' => $kode,
            'nama_menu' => 'Menu '.$kode,
            'kategori' => 'makanan',
            'harga' => 3000,
            'satuan' => 'item',
            'tersedia' => true,
            'stok' => $stok,
        ]);
    }

    private function buatPesanan(string $kode, int $jumlah = 2): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/pesanan', [
            'nama_pelanggan' => 'Pelanggan Test',
            'metode_pembayaran' => 'QRIS',
            'items' => [['kode_menu' => $kode, 'jumlah' => $jumlah]],
        ]);
    }

    public function test_pesanan_berhasil_dan_outbox_dibuat_dalam_transaksi(): void
    {
        $this->mock(OrderPublisher::class, fn ($mock) => $mock->shouldReceive('publish')->once()->andReturn(false));
        $this->menu('TEST001');

        $response = $this->buatPesanan('TEST001');

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['nama_pelanggan' => 'Pelanggan Test']);
        $this->assertDatabaseHas('order_items', ['nama_menu' => 'Menu TEST001', 'jumlah' => 2]);
        $outbox = OutboxMessage::sole();
        $this->assertSame('order.created', $outbox->event_name);
        $this->assertSame($outbox->event_id, $outbox->payload['event_id']);
        $this->assertSame($response->json('data.id'), $outbox->payload['data']['id']);
        $this->assertNull($outbox->published_at);
    }

    public function test_validasi_api_memakai_problem_details_dengan_errors(): void
    {
        $this->postJson('/api/v1/pesanan', ['items' => []])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonPath('instance', '/api/v1/pesanan')
            ->assertJsonStructure(['type', 'title', 'detail', 'errors' => ['nama_pelanggan', 'items']]);
    }

    public function test_stok_berkurang_saat_pesanan_dibuat(): void
    {
        $this->mock(OrderPublisher::class, fn ($mock) => $mock->shouldReceive('publish')->once());
        $this->menu('TEST002', 10);

        $this->buatPesanan('TEST002', 3)->assertCreated();

        $this->assertDatabaseHas('menus', ['kode' => 'TEST002', 'stok' => 7]);
    }

    public function test_stok_tidak_cukup_membatalkan_transaksi_pesanan_dan_outbox(): void
    {
        $this->mock(OrderPublisher::class, fn ($mock) => $mock->shouldNotReceive('publish'));
        $this->menu('TEST003', 2);

        $this->buatPesanan('TEST003', 5)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonStructure(['errors' => ['items']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->assertDatabaseHas('menus', ['kode' => 'TEST003', 'stok' => 2]);
    }

    public function test_pesanan_tidak_ditemukan_memakai_problem_details(): void
    {
        $this->getJson('/api/v1/pesanan/999999')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404)
            ->assertJsonPath('instance', '/api/v1/pesanan/999999');
    }

    public function test_rute_api_tidak_dikenal_memakai_problem_details(): void
    {
        $this->getJson('/api/v1/tidak-ada')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404)
            ->assertJsonPath('instance', '/api/v1/tidak-ada');
    }

    public function test_perubahan_status_yang_ditolak_memakai_problem_details(): void
    {
        $order = Order::create([
            'nama_pelanggan' => 'Pelanggan Test',
            'nama_menu' => 'Menu Test',
            'jumlah' => 1,
            'harga' => 5000,
            'total_harga' => 5000,
            'status' => 'selesai',
        ]);

        $this->postJson('/api/v1/pesanan/'.$order->id.'/perubahan-status', ['status' => 'dimasak'])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('status_sekarang', 'selesai')
            ->assertJsonPath('status_diminta', 'dimasak');
    }

    public function test_pembatalan_mengunci_pesanan_mengembalikan_stok_satu_kali(): void
    {
        $this->mock(OrderPublisher::class, fn ($mock) => $mock->shouldReceive('publish')->once());
        $this->menu('TEST008', 10);
        $created = $this->buatPesanan('TEST008', 3)->assertCreated();
        $orderId = $created->json('data.id');
        $this->assertDatabaseHas('menus', ['kode' => 'TEST008', 'stok' => 7]);

        $this->postJson('/api/v1/pesanan/'.$orderId.'/perubahan-status', ['status' => 'dibatalkan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan');
        $this->assertDatabaseHas('menus', ['kode' => 'TEST008', 'stok' => 10]);

        $this->postJson('/api/v1/pesanan/'.$orderId.'/perubahan-status', ['status' => 'dibatalkan'])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json');
        $this->assertDatabaseHas('menus', ['kode' => 'TEST008', 'stok' => 10]);
    }

    public function test_endpoint_lama_mencatat_outbox_dan_kompatibel_dengan_publisher_baru(): void
    {
        $this->mock(OrderPublisher::class, fn ($mock) => $mock->shouldReceive('publish')->once()->andReturn(true));

        $response = $this->postJson('/api/orders', [
            'nama_pelanggan' => 'Pelanggan Legacy',
            'nama_menu' => 'Ceker',
            'jumlah' => 2,
            'harga' => 4000,
            'metode_pembayaran' => 'tunai',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertSame('order.created', OutboxMessage::sole()->event_name);
        $this->assertSame($response->json('data.id'), OutboxMessage::sole()->payload['data']['id']);
    }

    public function test_publisher_mencatat_outbox_pending_saat_broker_tidak_tersedia(): void
    {
        $outbox = OutboxMessage::create([
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_name' => 'order.created',
            'aggregate_type' => 'order',
            'aggregate_id' => '999',
            'payload' => ['event_id' => 'test', 'event' => 'order.created', 'data' => ['id' => 999], 'occurred_at' => now()->toISOString()],
        ]);
        config(['rabbitmq.host' => '127.0.0.1', 'rabbitmq.port' => 1]);

        $this->assertFalse(app(OrderPublisher::class)->publish($outbox));

        $outbox->refresh();
        $this->assertNull($outbox->published_at);
        $this->assertSame(1, $outbox->attempts);
        $this->assertNotEmpty($outbox->last_error);
    }
}
