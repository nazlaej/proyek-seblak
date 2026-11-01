<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderOutbox;
use App\Services\OrderPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request, OrderPublisher $publisher, OrderOutbox $outbox)
    {
        $validated = $request->validate([
            'nama_pelanggan' => 'required|string|max:255',
            'nama_menu' => 'required|string|max:255',
            'jumlah' => 'required|integer|min:1',
            'harga' => 'required|numeric|min:0',
            'metode_pembayaran' => 'nullable|string|max:50',
        ]);

        $result = DB::transaction(function () use ($validated, $outbox) {
            $validated['total_harga'] =
                $validated['jumlah'] * $validated['harga'];

            $validated['status'] = 'menunggu';

            $order = Order::create($validated);

            return [
                'order' => $order->load('items'),
                'outbox' => $outbox->recordCreated($order),
            ];
        });

        $publisher->publish($result['outbox']);
        $order = $result['order'];

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $order,
        ], 201);
    }
}
