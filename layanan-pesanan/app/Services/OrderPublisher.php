<?php

namespace App\Services;

use App\Events\OrderCreated;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class OrderPublisher
{
    private const EXCHANGE = 'seblak.events';

    private const ROUTING_KEY = 'order.created';

    public function publish(OrderCreated $event): void
    {
        $startedAt = microtime(true);

        Log::info('RabbitMQ publish dimulai', [
            'exchange' => self::EXCHANGE,
            'routing_key' => self::ROUTING_KEY,
            'order_id' => $event->order['id'] ?? null,
        ]);

        try {
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                config('rabbitmq.vhost')
            );
        } catch (Throwable $e) {
            Log::error('RabbitMQ gagal terhubung', [
                'host' => config('rabbitmq.host'),
                'port' => config('rabbitmq.port'),
                'order_id' => $event->order['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            report($e);

            return;
        }

        $channel = null;
        $confirmed = false;

        try {
            $channel = $connection->channel();

            // Membuat exchange untuk event aplikasi.
            $channel->exchange_declare(
                self::EXCHANGE,
                'topic',
                false,
                true,
                false
            );

            // Mengaktifkan publisher confirms agar broker mengonfirmasi penerimaan.
            $channel->confirm_select();

            // Menyiapkan isi pesan dalam format JSON.
            $payload = [
                'event' => 'order.created',
                'data' => $event->order,
                'occurred_at' => now()->toISOString(),
            ];

            $message = new AMQPMessage(
                json_encode($payload, JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]
            );

            // Mengirim event ke exchange RabbitMQ.
            $channel->basic_publish(
                $message,
                self::EXCHANGE,
                self::ROUTING_KEY
            );

            // Menunggu konfirmasi dari broker.
            $channel->wait_for_pending_acks();

            $confirmed = true;

            Log::info('RabbitMQ publish berhasil dikonfirmasi broker', [
                'exchange' => self::EXCHANGE,
                'routing_key' => self::ROUTING_KEY,
                'order_id' => $event->order['id'] ?? null,
                'durasi_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]);
        } catch (Throwable $e) {
            Log::error('RabbitMQ publish gagal', [
                'exchange' => self::EXCHANGE,
                'routing_key' => self::ROUTING_KEY,
                'order_id' => $event->order['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            report($e);
        } finally {
            // Menutup channel jika masih terbuka.
            if ($channel !== null) {
                try {
                    $channel->close();
                } catch (Throwable $e) {
                    report($e);
                }
            }

            // Menutup koneksi RabbitMQ.
            try {
                if ($connection->isConnected()) {
                    $connection->close();
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (!$confirmed) {
            Log::warning('RabbitMQ pesan tidak terkonfirmasi dan dianggap gagal dikirim', [
                'order_id' => $event->order['id'] ?? null,
            ]);
        }
    }
}