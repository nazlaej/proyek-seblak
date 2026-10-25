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
        Schema::table('jurnal_penjualan', function (Blueprint $table) {
            // Nama menu pesanan bisa lebih panjang dari 7 karakter (batas lama sku).
            $table->renameColumn('sku', 'nama_menu');
        });

        Schema::table('jurnal_penjualan', function (Blueprint $table) {
            $table->string('nama_menu', 100)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jurnal_penjualan', function (Blueprint $table) {
            $table->string('nama_menu', 7)->change();
        });

        Schema::table('jurnal_penjualan', function (Blueprint $table) {
            $table->renameColumn('nama_menu', 'sku');
        });
    }
};
