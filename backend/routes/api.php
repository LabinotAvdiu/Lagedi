<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SalonController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::get('/salons',       [SalonController::class, 'search']);
Route::get('/salons/photo', [SalonController::class, 'photo']);
