<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\V1\MenuController as V1MenuController;
use App\Http\Controllers\Api\V1\PesananController;

// API lama — tetap dipertahankan
Route::get('/menus', [MenuController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);

// API versi 1
Route::prefix('v1')->group(function () {
    Route::get('/menu', [V1MenuController::class, 'index']);

    Route::post('/pesanan', [PesananController::class, 'store']);
    Route::get('/pesanan', [PesananController::class, 'index']);
    Route::get('/pesanan/{nomor}', [PesananController::class, 'show']);
    Route::post('/pesanan/{nomor}/perubahan-status', [
        PesananController::class,
        'ubahStatus',
    ]);
});