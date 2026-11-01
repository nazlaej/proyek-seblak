<?php

namespace App\Console\Commands;

use App\Models\AntrianDapur;
use Closure;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class DapurConsumer extends Command
{
    protected $signature = 'dapur:consume-orders';

    protected $description = 'Menerima event order.created dari RabbitMQ dan menyimpannya ke antrian dapur';

    /** Simpan satu envelope event order.created; kunci unik order_id melindungi replay serentak. */
    public function processEvent(array $event): bool
    {
        if (! isset($event['data']) || ! is_array($event['data'])) {
            throw new \RuntimeException('Payload pesanan tidak valid.');
        }

        $data = $event['data'];
        if (! isset($data['id'])) {
            throw new \RuntimeException('Payload pesanan tidak memiliki id.');
        }

        $antrian = AntrianDapur::firstOrCreate(
            ['order_id' => $data['id']],
            [
                'nama_pelanggan' => $data['nama_pelanggan'] ?? '-',
                'detail_item' => $data['items'] ?? [],
                'status' => 'menunggu',
            ],
        );

        return $antrian->wasRecentlyCreated;
    }

    /** Callback ini dipisah agar alur decode, ACK, dan duplicate dapat diuji tanpa broker. */
    public function consumerCallback(): Closure
    {
        return function (AMQPMessage $message): void {
            try {
                $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \RuntimeException('Payload pesanan tidak valid.');
                }

                $created = $this->processEvent($payload);
                $orderId = $payload['data']['id'] ?? '?';

                if ($created) {
                    $this->info('PESANAN MASUK KE DAPUR: pesanan #'.$orderId.' atas nama '.($payload['data']['nama_pelanggan'] ?? '-'));
                } else {
                    $this->line('Pesanan #'.$orderId.' sudah tercatat; duplikat dilewati.');
                }

                // Event baru maupun duplikat dianggap selesai setelah proses idempoten berhasil.
                $message->ack();
            } catch (Throwable $e) {
                // Pesan tidak valid atau kegagalan simpan tidak diulang tanpa batas.
                $message->nack(false);
                $this->error('Gagal memproses pesanan: '.$e->getMessage());
            }
        };
    }

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
        $channel->exchange_declare('seblak.events', 'topic', false, true, false);
        $channel->queue_declare('dapur.orders', false, true, false, false);
        $channel->queue_bind('dapur.orders', 'seblak.events', 'order.created');
        $channel->basic_qos(null, 1, null);

        $this->info('Dapur menunggu pesanan... Tekan Ctrl+C untuk berhenti.');
        $channel->basic_consume('dapur.orders', '', false, false, false, false, $this->consumerCallback());

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
