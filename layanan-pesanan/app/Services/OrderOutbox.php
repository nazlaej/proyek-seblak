<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\OutboxMessage;
use Illuminate\Support\Str;

class OrderOutbox
{
    /** Catat event dalam transaksi pemanggil yang sama dengan perubahan pesanan. */
    public function recordCreated(Order $order): OutboxMessage
    {
        $order->loadMissing('items');
        $eventId = (string) Str::uuid();
        $event = new OrderCreated($order->toArray());

        return OutboxMessage::create([
            'event_id' => $eventId,
            'event_name' => 'order.created',
            'aggregate_type' => 'order',
            'aggregate_id' => (string) $order->id,
            'payload' => [
                'event_id' => $eventId,
                'event' => 'order.created',
                'data' => $event->order,
                'occurred_at' => now()->toISOString(),
            ],
        ]);
    }
}
