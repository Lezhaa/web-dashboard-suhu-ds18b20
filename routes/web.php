<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuhuController;

// Dashboard
Route::get('/', [SuhuController::class, 'index'])->name('dashboard');

// Store data - POST ONLY dengan CSRF
Route::post('/suhu', [SuhuController::class, 'store'])->name('suhu.store');

// Redirect jika ada yang akses GET ke /suhu
Route::get('/suhu', function() {
    return redirect('/')->with('error', 'Gunakan form untuk input data!');
});

// API routes
Route::get('/api/suhu/today', [SuhuController::class, 'getTodayTemperatures']);
Route::get('/api/suhu/monthly', [SuhuController::class, 'getTemperatureData']);
Route::get('/api/suhu/realtime', [SuhuController::class, 'getRealtimeSuhuFirebase']);

// Export
Route::get('/export/excel', [SuhuController::class, 'exportExcel']);