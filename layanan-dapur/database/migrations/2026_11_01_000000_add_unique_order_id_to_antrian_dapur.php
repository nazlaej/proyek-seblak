<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kompatibilitas dengan migrasi 2026_10_31_000001 yang sudah terpasang di
        // database lain: jangan membuat constraint order_id yang kedua.
        if (collect(Schema::getIndexes('antrian_dapur'))->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['order_id'],
        )) {
            return;
        }

        Schema::table('antrian_dapur', function (Blueprint $table) {
            $table->unique('order_id', 'antrian_dapur_order_id_unique');
        });
    }

    public function down(): void
    {
        $createdHere = collect(Schema::getIndexes('antrian_dapur'))
            ->contains(fn (array $index) => $index['name'] === 'antrian_dapur_order_id_unique');

        if ($createdHere) {
            Schema::table('antrian_dapur', function (Blueprint $table) {
                $table->dropUnique('antrian_dapur_order_id_unique');
            });
        }
    }
};
