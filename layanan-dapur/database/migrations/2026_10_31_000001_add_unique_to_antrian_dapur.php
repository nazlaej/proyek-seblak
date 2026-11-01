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
        // Database lokal yang lebih baru mungkin sudah memakai constraint dari migrasi
        // 2026_11_01_000000. Catat migrasi ini tanpa membuat indeks unik kedua.
        if (collect(Schema::getIndexes('antrian_dapur'))->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['order_id'],
        )) {
            return;
        }

        Schema::table('antrian_dapur', function (Blueprint $table) {
            $table->unique('order_id', 'unique_order_id_dapur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $createdHere = collect(Schema::getIndexes('antrian_dapur'))
            ->contains(fn (array $index) => $index['name'] === 'unique_order_id_dapur');

        if ($createdHere) {
            Schema::table('antrian_dapur', function (Blueprint $table) {
                $table->dropUnique('unique_order_id_dapur');
            });
        }
    }
};
