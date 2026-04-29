<?php

use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::post('/auth', [RoomController::class, 'apiAuth']);
