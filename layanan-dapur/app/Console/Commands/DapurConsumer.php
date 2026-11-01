<?php

namespace App\Console\Commands;

use App\Models\AntrianDapur;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class DapurConsumer extends Command
{
    protected $signature = 'dapur:consume-orders';

    protected $description = 'Menerima event order.created dari RabbitMQ dan menyimpannya ke antrian dapur';

    // ===== METHOD UNTUK TEST & PANGGILAN CALLBACK =====
    public function processEvent(array $event, string $messageId = ''): bool
    {
        $payload = $event;

        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            throw new \RuntimeException('Payload pesanan tidak valid.');
        }

        $data = $payload['data'];

        if (!isset($data['id'])) {
            throw new \RuntimeException('Payload pesanan tidak memiliki id.');
        }

        // Cek idempoten: order_id sudah ada? (hindari pencatatan ganda)
        if (AntrianDapur::where('order_id', $data['id'])->exists()) {
            return false; // sudah diproses, abaikan duplikat
        }

        AntrianDapur::create([
            'order_id' => $data['id'],
            'nama_pelanggan' => $data['nama_pelanggan'] ?? '-',
            'detail_item' => $data['items'] ?? [],
            'status' => 'menunggu',
        ]);

        return true;
    }
    // ===================================

    public function handle(): int
    {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost')
        );

        $channel = $connection->channel();

        // Exchange yang sama dengan layanan Kasir (topic, durable).
        $channel->exchange_declare('seblak.events', 'topic', false, true, false);

        // Queue KHUSUS Dapur agar tidak berebut pesan dengan consumer lain.
        $channel->queue_declare('dapur.orders', false, true, false, false);

        // Bind queue Dapur ke exchange dengan routing key order.created.
        $channel->queue_bind('dapur.orders', 'seblak.events', 'order.created');

        // Proses satu pesan sekaligus agar adil dan cepat.
        $channel->basic_qos(null, 1, null);

        $this->info('Dapur menunggu pesanan... Tekan Ctrl+C untuk berhenti.');

        $callback = function (AMQPMessage $message) {
            try {
                $payload = json_decode($message->getBody(), true);

                if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
                    throw new \RuntimeException('Payload pesanan tidak valid.');
                }

                $data = $payload['data'];
                $messageId = $payload['message_id'] ?? $message->getMessageId();

                // Panggil processEvent yang sudah idempotent (cek duplikat sebelum simpan)
                $created = $this->processEvent($data, $messageId);

                if ($created) {
                    $this->info(
                        'PESANAN MASUK KE DAPUR: pesanan #' . ($data['id'] ?? '?') .
                        ' atas nama ' . ($data['nama_pelanggan'] ?? '-')
                    );
                } else {
                    $this->info('Duplikat diabaikan: pesanan #' . ($data['id'] ?? '?'));
                }

                // ACK hanya setelah diproses (atau duplikat diabaikan).
                $message->ack();
            } catch (Throwable $e) {
                // NACK tanpa requeue agar pesan gagal tidak berputar tanpa akhir.
                $message->nack(false);

                $this->error('Gagal memproses pesanan: ' . $e->getMessage());
            }
        };

        $channel->basic_consume('dapur.orders', '', false, false, false, false, $callback);

        try {
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $channel->close();
            $connection->close();
        }

        return self::SUCCESS;
    }
}