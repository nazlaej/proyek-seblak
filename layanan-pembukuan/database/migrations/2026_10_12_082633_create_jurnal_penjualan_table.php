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

            $hasUniqueMessageId = collect(Schema::getIndexes('jurnal_penjualan'))
                ->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['message_id']);

            if (! $hasUniqueMessageId) {
                throw new RuntimeException(
                    'Tabel jurnal_penjualan tidak memiliki indeks unik message_id; migrasi dihentikan agar idempotensi tidak berubah.'
                );
            }

            // Baseline lama dapat tertinggal Pending di ledger walaupun migrasi penerus
            // sudah membuat dan mengubah tabel ini. Adopsi tanpa menulis ulang data.
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
        // up() dapat mengadopsi tabel yang sudah berisi jurnal. Jangan menjatuhkan
        // data tersebut saat rollback batch yang mencatat migrasi kompatibilitas ini.
    }
};
