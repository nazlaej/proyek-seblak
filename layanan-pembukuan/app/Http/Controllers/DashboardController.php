<?php

namespace App\Http\Controllers;

use App\Models\JurnalPenjualan;

/**
 * Dashboard baca-saja untuk layanan pembukuan: menampilkan jurnal penjualan
 * yang dicatat oleh consumer RabbitMQ (pembukuan:konsumsi).
 */
class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function jurnal()
    {
        return [
            'baris' => JurnalPenjualan::orderByDesc('terjadi_pada')->orderByDesc('id')->limit(200)->get(),
            'jumlah_transaksi' => JurnalPenjualan::count(),
            'total_omzet' => (int) JurnalPenjualan::sum('total'),
            'total_unit' => (int) JurnalPenjualan::sum('jumlah'),
            'produk_unik' => (int) JurnalPenjualan::distinct('nama_menu')->count('nama_menu'),
        ];
    }
}
