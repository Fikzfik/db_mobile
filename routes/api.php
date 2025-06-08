<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarnetController;
use App\Http\Controllers\PcController;
use App\Http\Controllers\BookedPcController;
use App\Http\Controllers\BookedConsoleController;

Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);

// Route yang memerlukan autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::get('/user', [UserController::class, 'user']);
});
Route::get('/history/{userId}', [BookedPcController::class, 'getHistory']);
// Route publik
Route::get('/warnets', [WarnetController::class, 'index']);
Route::get('/pcs', [PcController::class, 'index']);
Route::get('/booked_pc', [BookedPcController::class, 'getBookedTimes']);
Route::get('/booked_console', [BookedConsoleController::class, 'index']);
Route::post('/book_pcs', [BookedPcController::class, 'store']);