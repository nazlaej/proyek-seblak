<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Services\OrderPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request, OrderPublisher $publisher)
    {
        $validated = $request->validate([
            'nama_pelanggan' => 'required|string|max:255',
            'nama_menu' => 'required|string|max:255',
            'jumlah' => 'required|integer|min:1',
            'harga' => 'required|numeric|min:0',
            'metode_pembayaran' => 'nullable|string|max:50',
        ]);

        $order = DB::transaction(function () use ($validated) {
            $validated['total_harga'] =
                $validated['jumlah'] * $validated['harga'];

            $validated['status'] = 'menunggu';

            return Order::create($validated);
        });

        $publisher->publish(
            new OrderCreated($order->toArray())
        );

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $order,
        ], 201);
    }
}