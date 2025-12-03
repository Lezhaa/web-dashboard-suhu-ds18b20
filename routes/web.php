<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuhuController;

/*
 Web Routes
*/

// Dashboard
Route::get('/', [SuhuController::class, 'index']);

// Form Submit input manual
Route::post('/suhu', [SuhuController::class, 'store']);

// API Endpoints untuk Dashboard
Route::get('/api/suhu/today', [SuhuController::class, 'getTodayTemperatures']);
Route::get('/api/suhu/monthly', [SuhuController::class, 'getTemperatureData']);
Route::get('/api/suhu/realtime', [SuhuController::class, 'getRealtimeSuhu']);

// Export Excel
Route::get('/export/excel', [SuhuController::class, 'exportExcel']);