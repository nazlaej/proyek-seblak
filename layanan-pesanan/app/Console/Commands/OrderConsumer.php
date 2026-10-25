<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class OrderConsumer extends Command
{
    protected $signature = 'rabbitmq:consume-orders';

    protected $description = 'Menerima event pesanan dari RabbitMQ';

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

        $channel->exchange_declare(
            'seblak.events',
            'topic',
            false,
            true,
            false
        );

        $channel->queue_declare(
            'seblak.orders',
            false,
            true,
            false,
            false
        );

        $channel->queue_bind(
            'seblak.orders',
            'seblak.events',
            'order.created'
        );

        // Memproses satu pesan sekaligus per consumer agar adil dan cepat.
        $channel->basic_qos(null, 1, null);

        $this->info('Menunggu pesanan dari RabbitMQ... Tekan Ctrl+C untuk berhenti.');

        Log::info('RabbitMQ consumer siap menerima pesan', [
            'queue' => 'seblak.orders',
            'routing_key' => 'order.created',
            'pid' => getmypid(),
        ]);

        $callback = function (AMQPMessage $message) {
            $startedAt = microtime(true);

            try {
                $data = json_decode($message->getBody(), true);

                if (!is_array($data)) {
                    throw new \RuntimeException('Payload pesan bukan JSON yang valid.');
                }

                $this->info('Pesanan diterima:');
                $this->line(json_encode($data, JSON_PRETTY_PRINT));

                $message->ack();

                Log::info('RabbitMQ pesan diproses dan di-ACK', [
                    'event' => $data['event'] ?? null,
                    'order_id' => $data['data']['id'] ?? null,
                    'routing_key' => $message->get('routing_key'),
                    'durasi_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                    'pid' => getmypid(),
                ]);
            } catch (Throwable $e) {
                // Menolak pesan tanpa requeue agar tidak berputar tanpa akhir,
                // namun tetap tercatat agar tidak hilang secara diam-diam.
                $message->nack(false);

                $this->error('Gagal memproses pesan: ' . $e->getMessage());

                Log::error('RabbitMQ pesan gagal diproses dan di-NACK', [
                    'error' => $e->getMessage(),
                    'body' => $message->getBody(),
                    'pid' => getmypid(),
                ]);
            }
        };

        $channel->basic_consume(
            'seblak.orders',
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        try {
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $channel->close();
            $connection->close();

            Log::info('RabbitMQ consumer berhenti', [
                'pid' => getmypid(),
            ]);
        }

        return self::SUCCESS;
    }
}
