<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

// Rute untuk menampilkan halaman utama
Route::get('/', [ChatController::class, 'index']);
Route::get('/api/climate-data', [ChatController::class, 'getClimateData']);
Route::get('/api/search-kecamatan', [ChatController::class, 'searchKecamatan']);