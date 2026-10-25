<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\OrderCreated;
use App\Models\Menu;
use App\Models\Order;
use App\Services\OrderPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        $hasil = DB::transaction(function () use ($validated) {
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

            return $pesanan->load('items');
        });

        $publisher->publish(
            new OrderCreated($hasil->toArray())
        );

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
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
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
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
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
                'message' => 'Perubahan status tidak diperbolehkan.',
                'status_sekarang' => $statusSekarang,
                'status_diminta' => $statusBaru,
            ], 409);
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