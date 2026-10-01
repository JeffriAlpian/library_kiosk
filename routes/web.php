<?php

use App\Http\Controllers\Admin\AdminComputerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionPhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('sesi', [AdminSessionController::class, 'index'])->name('sesi.index');
    Route::get('sesi/{session}', [AdminSessionController::class, 'show'])->name('sesi.show');
    Route::get('sesi/{session}/foto', SessionPhotoController::class)->name('sesi.foto');

    Route::get('komputer', [AdminComputerController::class, 'index'])->name('komputer.index');
    Route::patch('komputer/{computer}/status', [AdminComputerController::class, 'updateStatus'])
        ->name('komputer.status');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
