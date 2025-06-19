<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarnetController;
use App\Http\Controllers\PcController;
use App\Http\Controllers\PsController;
use App\Http\Controllers\BookedPcController;
use App\Http\Controllers\BookedPsController;
use App\Http\Controllers\TopUpController;
use App\Http\Controllers\JokiController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\FriendRequestController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\CommunityChatController;

Route::get('/warnets', [WarnetController::class, 'index']);
Route::get('/pcs', [PcController::class, 'index']);
Route::get('/playstations', [PsController::class, 'show']);
Route::get('/booked_pc', [BookedPcController::class, 'getBookedTimes']);
Route::post('/book_pcs', [BookedPcController::class, 'store']);
Route::get('/booked_console', [BookedPsController::class, 'getBookedTimes']);
Route::post('/book_ps', [BookedPsController::class, 'store']);
Route::get('/jasa_joki', [JokiController::class, 'index']);
Route::post('/jasa_joki', [JokiController::class, 'store']);
Route::get('/jasa_joki/{id}', [JokiController::class, 'show']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::get('/user', [UserController::class, 'user']);
    Route::put('/user', [UserController::class, 'updateProfile']); // New route for profile update
    Route::post('/topup', [TopUpController::class, 'store']);
    Route::get('/history/{userId}', [BookedPcController::class, 'getHistory']);
    Route::get('/ps_history/{userId}', [BookedPsController::class, 'getHistory']);
    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends', [FriendController::class, 'store']);
    Route::get('/friend_requests', [FriendRequestController::class, 'index']);
    Route::post('/friend_requests', [FriendRequestController::class, 'store']);
    Route::put('/friend_requests/{id}', [FriendRequestController::class, 'update']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::get('/community-chats', [CommunityChatController::class, 'index']);
    Route::post('/community-chats', [CommunityChatController::class, 'store']);
});