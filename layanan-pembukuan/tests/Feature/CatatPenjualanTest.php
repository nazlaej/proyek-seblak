<?php

namespace Tests\Feature;

use App\Models\JurnalPenjualan;
use App\Pembukuan\CatatPenjualan;
use App\Pembukuan\PesanTidakValid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatatPenjualanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membentuk event order.created sesuai format layanan pesanan:
     * { event, data, occurred_at }.
     *
     * Pada event Seblak, harga/total_harga sudah berupa TOTAL pesanan
     * (bukan harga satuan), jadi tidak dikalikan dengan jumlah.
     */
    private function eventPesanan(array $ubah = []): array
    {
        $data = array_merge([
            'id' => 1,
            'nama_menu' => 'Ceker',
            'jumlah' => 3,
            'harga' => 6000,
            'total_harga' => 6000,
        ], $ubah);

        return [
            'event' => 'order.created',
            'data' => $data,
            'occurred_at' => '2026-10-01T10:15:00+07:00',
        ];
    }

    public function test_penjualan_dicatat_ke_jurnal(): void
    {
        $baru = app(CatatPenjualan::class)($this->eventPesanan(), 'pesan-1');

        $this->assertTrue($baru);
        $jurnal = JurnalPenjualan::sole();
        $this->assertSame('Ceker', $jurnal->nama_menu);
        $this->assertSame(3, $jurnal->jumlah);
        $this->assertSame(6000, $jurnal->total);
    }

    public function test_pesan_yang_sama_tidak_dicatat_dua_kali(): void
    {
        $catat = app(CatatPenjualan::class);

        $catat($this->eventPesanan(), 'pesan-1');
        $baru = $catat($this->eventPesanan(), 'pesan-1');

        $this->assertFalse($baru);
        $this->assertSame(1, JurnalPenjualan::count());
    }

    public function test_tanpa_message_id_memakai_id_pesanan_sebagai_kunci(): void
    {
        $catat = app(CatatPenjualan::class);

        $catat($this->eventPesanan(['id' => 99]), '');
        $baru = $catat($this->eventPesanan(['id' => 99]), '');

        // Pesan dengan id pesanan sama tidak dicatat dua kali.
        $this->assertFalse($baru);
        $this->assertSame(1, JurnalPenjualan::count());

        $jurnal = JurnalPenjualan::sole();
        $this->assertSame('order-99', $jurnal->message_id);
    }

    public function test_pesan_tanpa_field_wajib_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);

        $event = $this->eventPesanan();
        unset($event['data']['harga']);

        app(CatatPenjualan::class)($event, 'pesan-3');
    }

    public function test_pesan_tanpa_data_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);

        app(CatatPenjualan::class)(['event' => 'order.created'], 'pesan-4');
    }
}
