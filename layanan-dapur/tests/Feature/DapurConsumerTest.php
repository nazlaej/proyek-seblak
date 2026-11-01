<?php

namespace Tests\Feature;

use App\Models\AntrianDapur;
use App\Console\Commands\DapurConsumer;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class DapurConsumerTest extends TestCase
{
    use RefreshDatabase;

    private function eventPesanan(array $ubah = []): array
    {
        return array_merge([
            'event_id' => 'f829dc8a-3268-4f47-b170-6cc4b9625c2e',
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
        $event = $this->eventPesanan();
        
        $consumer->processEvent($event);
        $result = $consumer->processEvent($event); // kirim 2x
        
        $this->assertFalse($result);
        $this->assertEquals(1, AntrianDapur::where('order_id', 99)->count());
    }

    public function test_duplikat_dijaga_kunci_unik_database(): void
    {
        $consumer = new DapurConsumer();
        $event = $this->eventPesanan();

        $consumer->processEvent($event);
        $consumer->processEvent($event);

        $this->assertDatabaseCount('antrian_dapur', 1);
        $indexes = collect(\Illuminate\Support\Facades\Schema::getIndexes('antrian_dapur'))
            ->filter(fn ($index) => $index['unique'] && $index['columns'] === ['order_id']);
        $this->assertCount(1, $indexes);
    }

    public function test_callback_meneruskan_envelope_utuh_dan_ack_setelah_disimpan(): void
    {
        $consumer = new DapurConsumer();
        $consumer->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
        $message = \Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->once()->andReturn(json_encode($this->eventPesanan(), JSON_THROW_ON_ERROR));
        $message->shouldReceive('ack')->once();
        $message->shouldNotReceive('nack');

        ($consumer->consumerCallback())($message);

        $this->assertDatabaseHas('antrian_dapur', ['order_id' => 99, 'nama_pelanggan' => 'Test Pelanggan']);
        $this->assertDatabaseCount('antrian_dapur', 1);
    }

    public function test_callback_ack_duplikat_tanpa_menambah_antrean(): void
    {
        $consumer = new DapurConsumer();
        $consumer->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
        $callback = $consumer->consumerCallback();
        $event = json_encode($this->eventPesanan(), JSON_THROW_ON_ERROR);

        foreach (range(1, 2) as $_) {
            $message = \Mockery::mock(AMQPMessage::class);
            $message->shouldReceive('getBody')->once()->andReturn($event);
            $message->shouldReceive('ack')->once();
            $message->shouldNotReceive('nack');
            $callback($message);
        }

        $this->assertDatabaseCount('antrian_dapur', 1);
        $this->assertDatabaseHas('antrian_dapur', ['order_id' => 99]);
    }
}
