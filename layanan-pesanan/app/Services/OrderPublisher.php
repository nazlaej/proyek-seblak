<?php

namespace App\Services;

use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class OrderPublisher
{
    private const EXCHANGE = 'seblak.events';

    public function publish(OutboxMessage $outboxMessage): bool
    {
        $connection = null;
        $channel = null;

        try {
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                config('rabbitmq.vhost')
            );

            $channel = $connection->channel();
            $channel->exchange_declare(
                self::EXCHANGE,
                'topic',
                false,
                true,
                false
            );
            $channel->confirm_select();

            $returned = null;
            $channel->set_return_listener(
                function ($replyCode, $replyText) use (&$returned): void {
                    $returned = "RabbitMQ menolak routing ({$replyCode}): {$replyText}";
                }
            );

            $message = new AMQPMessage(
                json_encode($outboxMessage->payload, JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $outboxMessage->event_id,
                    'type' => $outboxMessage->event_name,
                ]
            );

            $channel->basic_publish(
                $message,
                self::EXCHANGE,
                $outboxMessage->event_name,
                true,
                false
            );
            $channel->wait_for_pending_acks_returns();

            if ($returned !== null) {
                throw new \RuntimeException($returned);
            }

            $outboxMessage->forceFill([
                'attempts' => $outboxMessage->attempts + 1,
                'last_error' => null,
                'published_at' => now(),
            ])->save();

            Log::info('Outbox event dikonfirmasi RabbitMQ', [
                'event_id' => $outboxMessage->event_id,
                'event' => $outboxMessage->event_name,
                'aggregate_id' => $outboxMessage->aggregate_id,
            ]);

            return true;
        } catch (Throwable $e) {
            $outboxMessage->forceFill([
                'attempts' => $outboxMessage->attempts + 1,
                'last_error' => substr($e->getMessage(), 0, 65535),
            ])->save();

            Log::error('Outbox event gagal dikirim; pesan tetap tertunda', [
                'event_id' => $outboxMessage->event_id,
                'event' => $outboxMessage->event_name,
                'aggregate_id' => $outboxMessage->aggregate_id,
                'error' => $e->getMessage(),
            ]);

            report($e);

            return false;
        } finally {
            if ($channel !== null) {
                try {
                    $channel->close();
                } catch (Throwable $e) {
                    report($e);
                }
            }

            if ($connection !== null) {
                try {
                    if ($connection->isConnected()) {
                        $connection->close();
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }
}
