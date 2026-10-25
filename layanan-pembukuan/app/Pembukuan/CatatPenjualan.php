<?php

namespace App\Pembukuan;

use App\Models\JurnalPenjualan;
use Illuminate\Support\Carbon;

class CatatPenjualan
{
    private const FIELD_WAJIB = ['id', 'nama_menu', 'jumlah', 'harga'];

    /**
     * Mencatat satu event order.created dari layanan pesanan ke jurnal penjualan.
     * Mengembalikan true bila baris jurnal baru dibuat.
     */
    public function __invoke(array $event, string $messageId): bool
    {
        // Event seblak dibungkus dalam { event, data, occurred_at }.
        $data = $event['data'] ?? null;

        if (! is_array($data)) {
            throw new PesanTidakValid('Pesan tidak memiliki data pesanan.');
        }

        foreach (self::FIELD_WAJIB as $field) {
            if (! array_key_exists($field, $data)) {
                throw new PesanTidakValid("Field {$field} tidak ada.");
            }
        }

        // Kunci idempoten: satu pesanan = satu baris jurnal.
        // Pesanan tidak mengirim message_id, jadi pakai id pesanan.
        $kunci = $messageId !== '' ? $messageId : 'order-'.$data['id'];

        $jumlah = (int) $data['jumlah'];

        // Event Seblak: harga sudah berupa TOTAL pesanan (bukan harga satuan).
        // Ambil total_harga bila ada, jika tidak pakai harga. Jangan dikali jumlah lagi.
        $total = (int) round((float) ($data['total_harga'] ?? $data['harga']));

        $terjadiPada = $event['occurred_at'] ?? now()->toISOString();

        // firstOrCreate + kolom unik message_id = handler idempoten:
        // pesan yang dikirim ulang oleh broker tidak dicatat dua kali.
        $jurnal = JurnalPenjualan::firstOrCreate(
            ['message_id' => $kunci],
            [
                'nama_menu' => $data['nama_menu'],
                'jumlah' => $jumlah,
                'harga_satuan' => $total,
                'total' => $total,
                'terjadi_pada' => Carbon::parse($terjadiPada),
            ],
        );

        return $jurnal->wasRecentlyCreated;
    }
}