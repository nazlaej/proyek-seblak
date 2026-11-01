<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\OrderCreated;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Services\OrderPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PesananController extends Controller
{
    // Menampilkan semua pesanan
    public function index()
    {
        $pesanan = Order::with('items')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $pesanan,
        ], 200);
    }

    // Membuat pesanan baru
    public function store(Request $request, OrderPublisher $publisher)
    {
        $validated = $request->validate([
            'nama_pelanggan' => 'required|string|max:255',
            'metode_pembayaran' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.kode_menu' => 'required|string|exists:menus,kode',
            'items.*.jumlah' => 'required|integer|min:1',
        ]);

        $transaksi = DB::transaction(function () use ($validated) {
            $itemsPesanan = [];
            $totalHarga = 0;

            foreach ($validated['items'] as $item) {
                $menu = Menu::where('kode', $item['kode_menu'])
                    ->where('tersedia', true)
                    ->lockForUpdate()
                    ->first();

                if (!$menu) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Menu dengan kode ' .
                            $item['kode_menu'] .
                            ' tidak tersedia.',
                        ],
                    ]);
                }

                $jumlah = (int) $item['jumlah'];

                if ($menu->stok < $jumlah) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Stok ' . $menu->nama_menu . ' tinggal ' .
                            $menu->stok . ', tidak cukup untuk ' .
                            $jumlah . '.',
                        ],
                    ]);
                }

                $menu->decrement('stok', $jumlah);

                $harga = (float) $menu->harga;
                $subtotal = $jumlah * $harga;

                $itemsPesanan[] = [
                    'menu_id' => $menu->id,
                    'nama_menu' => $menu->nama_menu,
                    'jumlah' => $jumlah,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                ];

                $totalHarga += $subtotal;
            }

            $pesanan = Order::create([
                'nama_pelanggan' => $validated['nama_pelanggan'],
                'nama_menu' => implode(
                    ', ',
                    array_column($itemsPesanan, 'nama_menu')
                ),
                'jumlah' => array_sum(
                    array_column($itemsPesanan, 'jumlah')
                ),
                'harga' => $totalHarga,
                'total_harga' => $totalHarga,
                'metode_pembayaran' =>
                    $validated['metode_pembayaran'] ?? null,
                'status' => 'diterima',
            ]);

            foreach ($itemsPesanan as $item) {
                $pesanan->items()->create($item);
            }

            $pesanan->load('items');
            $event = new OrderCreated($pesanan->toArray());
            $eventId = (string) Str::uuid();
            $outboxMessage = OutboxMessage::create([
                'event_id' => $eventId,
                'event_name' => 'order.created',
                'aggregate_type' => 'order',
                'aggregate_id' => (string) $pesanan->id,
                'payload' => [
                    'event_id' => $eventId,
                    'event' => 'order.created',
                    'data' => $event->order,
                    'occurred_at' => now()->toISOString(),
                ],
            ]);

            return [
                'order' => $pesanan,
                'outbox' => $outboxMessage,
            ];
        });

        $hasil = $transaksi['order'];
        $publisher->publish($transaksi['outbox']);

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $hasil,
        ], 201)->header(
            'Location',
            url('/api/v1/pesanan/' . $hasil->id)
        );
    }

    // Menampilkan detail satu pesanan
    public function show(string $nomor)
    {
        $pesanan = Order::with('items')->find($nomor);

        if (!$pesanan) {
            return response()->json([
                'type'  => 'urn:seblak:problem:not-found',
                'title' => 'Pesanan tidak ditemukan',
                'status'=> 404,
                'detail'=> 'Pesanan dengan nomor tersebut tidak ada.',
                'instance'=> '/api/v1/pesanan/'.$nomor,
            ], 404, ['Content-Type' => 'application/problem+json']);
        }

        return response()->json([
            'data' => $pesanan,
        ], 200);
    }

    // Mengubah status pesanan
    public function ubahStatus(Request $request, string $nomor)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:dimasak,siap,selesai,dibatalkan',
        ]);

        $pesanan = Order::find($nomor);

        if (!$pesanan) {
            return response()->json([
                'type'  => 'urn:seblak:problem:not-found',
                'title' => 'Pesanan tidak ditemukan',
                'status'=> 404,
                'detail'=> 'Pesanan dengan nomor tersebut tidak ada.',
                'instance'=> '/api/v1/pesanan/'.$nomor,
            ], 404, ['Content-Type' => 'application/problem+json']);
        }

        $statusSekarang = $pesanan->status;
        $statusBaru = $validated['status'];

        $alurStatus = [
            'diterima' => ['dimasak', 'dibatalkan'],
            'dimasak' => ['siap'],
            'siap' => ['selesai'],
            'selesai' => [],
            'dibatalkan' => [],
        ];

        if (!in_array(
            $statusBaru,
            $alurStatus[$statusSekarang] ?? [],
            true
        )) {
            return response()->json([
                'type'  => 'urn:seblak:problem:conflict',
                'title' => 'Perubahan status tidak diperbolehkan',
                'status'=> 409,
                'detail'=> 'Status tidak dapat diubah dari kondisi saat ini.',
                'instance'=> '/api/v1/pesanan/'.$nomor.'/perubahan-status',
            ], 409, ['Content-Type' => 'application/problem+json']);
        }

        // Jika dibatalkan: kembalikan stok ke semua menu yang dipesan
        if ($statusBaru === 'dibatalkan') {
            DB::transaction(function () use ($pesanan) {
                foreach ($pesanan->items as $item) {
                    $menu = Menu::where('id', $item->menu_id)
                        ->lockForUpdate()
                        ->first();
                    if ($menu) {
                        $menu->increment('stok', $item->jumlah);
                    }
                }
            });
        }

        $pesanan->update([
            'status' => $statusBaru,
        ]);

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => $pesanan->fresh()->load('items'),
        ], 200);
    }
}
