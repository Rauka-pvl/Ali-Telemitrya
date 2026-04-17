<?php

use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/room/{roomId}', [RoomController::class, 'show']);
Route::post('/room/{roomId}/join', [RoomController::class, 'join']);
Route::post('/room/{roomId}/heartbeat', [RoomController::class, 'heartbeat']);
Route::post('/room/{roomId}/leave', [RoomController::class, 'leave']);
Route::post('/room/{roomId}/motion', [RoomController::class, 'motion']);
