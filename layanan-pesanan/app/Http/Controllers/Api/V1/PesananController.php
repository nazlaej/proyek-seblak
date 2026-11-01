<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Order;
use App\Services\OrderOutbox;
use App\Services\OrderPublisher;
use App\Support\ApiProblem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PesananController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Order::with('items')->orderByDesc('id')->get(),
        ], 200);
    }

    public function store(Request $request, OrderPublisher $publisher, OrderOutbox $outbox)
    {
        $validated = $request->validate([
            'nama_pelanggan' => 'required|string|max:255',
            'metode_pembayaran' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.kode_menu' => 'required|string|exists:menus,kode',
            'items.*.jumlah' => 'required|integer|min:1',
        ]);

        $transaksi = DB::transaction(function () use ($validated, $outbox) {
            $itemsPesanan = [];
            $totalHarga = 0;

            foreach ($validated['items'] as $item) {
                $menu = Menu::where('kode', $item['kode_menu'])
                    ->where('tersedia', true)
                    ->lockForUpdate()
                    ->first();

                if (! $menu) {
                    throw ValidationException::withMessages([
                        'items' => ['Menu dengan kode '.$item['kode_menu'].' tidak tersedia.'],
                    ]);
                }

                $jumlah = (int) $item['jumlah'];
                if ($menu->stok < $jumlah) {
                    throw ValidationException::withMessages([
                        'items' => ['Stok '.$menu->nama_menu.' tinggal '.$menu->stok.', tidak cukup untuk '.$jumlah.'.'],
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
                'nama_menu' => implode(', ', array_column($itemsPesanan, 'nama_menu')),
                'jumlah' => array_sum(array_column($itemsPesanan, 'jumlah')),
                'harga' => $totalHarga,
                'total_harga' => $totalHarga,
                'metode_pembayaran' => $validated['metode_pembayaran'] ?? null,
                'status' => 'diterima',
            ]);

            foreach ($itemsPesanan as $item) {
                $pesanan->items()->create($item);
            }

            $pesanan->load('items');

            return [
                'order' => $pesanan,
                'outbox' => $outbox->recordCreated($pesanan),
            ];
        });

        // Gagal publish tidak membatalkan pesanan; outbox tetap tertunda untuk retry manual.
        $publisher->publish($transaksi['outbox']);

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $transaksi['order'],
        ], 201)->header('Location', url('/api/v1/pesanan/'.$transaksi['order']->id));
    }

    public function show(string $nomor)
    {
        $pesanan = Order::with('items')->find($nomor);
        if (! $pesanan) {
            return ApiProblem::response(
                404,
                'Pesanan tidak ditemukan',
                'Pesanan dengan nomor tersebut tidak ada.',
                '/api/v1/pesanan/'.$nomor,
            );
        }

        return response()->json(['data' => $pesanan], 200);
    }

    public function ubahStatus(Request $request, string $nomor)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:dimasak,siap,selesai,dibatalkan',
        ]);
        $statusBaru = $validated['status'];

        // Pemeriksaan status, penguncian, pemulihan stok, dan perubahan status atomik.
        $hasil = DB::transaction(function () use ($nomor, $statusBaru) {
            $pesanan = Order::whereKey($nomor)->lockForUpdate()->first();
            if (! $pesanan) {
                return ['problem' => [
                    'status' => 404,
                    'title' => 'Pesanan tidak ditemukan',
                    'detail' => 'Pesanan dengan nomor tersebut tidak ada.',
                    'instance' => '/api/v1/pesanan/'.$nomor.'/perubahan-status',
                    'extensions' => [],
                ]];
            }

            $statusSekarang = $pesanan->status;
            $alurStatus = [
                'diterima' => ['dimasak', 'dibatalkan'],
                'dimasak' => ['siap'],
                'siap' => ['selesai'],
                'selesai' => [],
                'dibatalkan' => [],
            ];

            if (! in_array($statusBaru, $alurStatus[$statusSekarang] ?? [], true)) {
                return ['problem' => [
                    'status' => 409,
                    'title' => 'Perubahan status tidak diperbolehkan',
                    'detail' => 'Status tidak dapat diubah dari '.$statusSekarang.' menjadi '.$statusBaru.'.',
                    'instance' => '/api/v1/pesanan/'.$nomor.'/perubahan-status',
                    'extensions' => [
                        'status_sekarang' => $statusSekarang,
                        'status_diminta' => $statusBaru,
                    ],
                ]];
            }

            if ($statusBaru === 'dibatalkan') {
                $pesanan->load('items');
                $jumlahPerMenu = $pesanan->items
                    ->groupBy('menu_id')
                    ->map(fn ($items) => $items->sum('jumlah'))
                    ->sortKeys();

                foreach ($jumlahPerMenu as $menuId => $jumlah) {
                    $menu = Menu::whereKey($menuId)->lockForUpdate()->first();
                    if ($menu) {
                        $menu->increment('stok', (int) $jumlah);
                    }
                }
            }

            $pesanan->update(['status' => $statusBaru]);

            return ['order' => $pesanan->fresh()->load('items')];
        });

        if (isset($hasil['problem'])) {
            $problem = $hasil['problem'];

            return ApiProblem::response(
                $problem['status'],
                $problem['title'],
                $problem['detail'],
                $problem['instance'],
                $problem['extensions'],
            );
        }

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => $hasil['order'],
        ], 200);
    }
}
