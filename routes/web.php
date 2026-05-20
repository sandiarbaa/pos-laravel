<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\QrController;

// QR Web Routes (session-based auth, bukan sanctum)
Route::get('/qr/login', [QrController::class, 'loginForm'])->name('qr.login');
Route::post('/qr/login', [QrController::class, 'login'])->name('qr.login.post');
Route::get('/qr/sheet', [QrController::class, 'sheet'])->name('qr.sheet');
Route::get('/qr/logout', [QrController::class, 'logout'])->name('qr.logout');
