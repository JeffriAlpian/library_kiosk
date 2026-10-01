<?php

use App\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

// Publik: self-registration wizard (dilindungi registration secret, bukan token kiosk)
Route::post('/kiosk/self-register', [KioskController::class, 'selfRegister'])
    ->middleware('throttle:10,1');

// Otomatis berprefix /api  ->  /api/kiosk/identify, /api/sesi/mulai, /api/sesi/selesai
Route::middleware(['kiosk', 'throttle:120,1'])->group(function () {
    Route::post('/kiosk/identify', [KioskController::class, 'identify']);
    Route::post('/sesi/mulai', [KioskController::class, 'start']);
    Route::post('/sesi/selesai', [KioskController::class, 'finish']);
});
