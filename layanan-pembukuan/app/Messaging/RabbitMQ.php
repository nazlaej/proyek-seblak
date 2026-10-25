<?php

namespace App\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ
{
    public static function koneksi(): AMQPStreamConnection
    {
        $c = config('rabbitmq');

        return new AMQPStreamConnection($c['host'], $c['port'], $c['user'], $c['password'], $c['vhost']);
    }

    /**
     * Menyiapkan exchange, queue, dan binding. Aman dipanggil berulang kali:
     * declare tidak mengubah apa pun bila objeknya sudah ada dengan pengaturan yang sama.
     */
    public static function siapkanTopologi(AMQPChannel $channel): void
    {
        // Exchange event milik layanan pesanan (sama persis dengan yang dideklarasikan publisher).
        $channel->exchange_declare('seblak.events', 'topic', false, true, false);

        // Dead letter: exchange + queue tempat pesan yang ditolak dikumpulkan.
        $channel->exchange_declare('seblak.dlx', 'fanout', false, true, false);
        $channel->queue_declare('pembukuan.orders.dlq', false, true, false, false);
        $channel->queue_bind('pembukuan.orders.dlq', 'seblak.dlx');

        // Queue pembukuan: hanya menerima pesanan; pesan yang ditolak diarahkan ke seblak.dlx.
        // Queue terpisah agar tidak berebut pesan dengan consumer lain (pola publish-subscribe).
        $channel->queue_declare('pembukuan.orders', false, true, false, false, false,
            new AMQPTable(['x-dead-letter-exchange' => 'seblak.dlx']));
        $channel->queue_bind('pembukuan.orders', 'seblak.events', 'order.created');
    }
}
