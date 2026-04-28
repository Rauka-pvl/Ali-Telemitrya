<?php

use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/room/tele/{roomId}', [RoomController::class, 'telemetry']);
Route::get('/room/mic/{roomId}', [RoomController::class, 'mic']);
Route::get('/rooms/{roomId}', [RoomController::class, 'rooms']);
Route::get('/room/game/{roomId}', [RoomController::class, 'game']);
Route::get('/room/game/mic/{roomId}', [RoomController::class, 'gameMic']);
Route::get('/room/game/motion/{roomId}', [RoomController::class, 'gameMotion']);
Route::get('/room/player/{roomId}', [RoomController::class, 'player']);
Route::post('/room/{roomId}/player/join', [RoomController::class, 'playerJoin']);
Route::post('/room/{roomId}/player/heartbeat', [RoomController::class, 'playerHeartbeat']);
Route::post('/room/{roomId}/player/leave', [RoomController::class, 'playerLeave']);
Route::post('/room/{roomId}/motion', [RoomController::class, 'motion']);
Route::post('/room/{roomId}/mic-level', [RoomController::class, 'micLevel']);
Route::post('/room/{roomId}/movement', [RoomController::class, 'movement']);
