<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('jurnal_penjualan')) {
            $columns = Schema::getColumnListing('jurnal_penjualan');
            $wajib = [
                'id', 'message_id', 'jumlah', 'harga_satuan', 'total',
                'terjadi_pada', 'created_at', 'updated_at',
            ];

            if (array_diff($wajib, $columns) || ! array_intersect(['sku', 'nama_menu'], $columns)) {
                throw new RuntimeException(
                    'Tabel jurnal_penjualan sudah ada dengan skema yang tidak dikenal; migrasi dihentikan agar data tidak tertimpa.'
                );
            }

            // Mendukung basis data lama yang mencatat baseline dengan nama migrasi 2026_10_12.
            return;
        }

        Schema::create('jurnal_penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();   // kunci idempoten: satu pesan = satu baris
            $table->string('sku', 7);
            $table->unsignedInteger('jumlah');
            $table->unsignedBigInteger('harga_satuan');
            $table->unsignedBigInteger('total');
            $table->timestamp('terjadi_pada');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Baseline ini dapat mengadopsi tabel lama. Hindari drop otomatis yang berisiko menghapus jurnal.
    }
};
