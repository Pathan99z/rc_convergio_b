<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('verify', [AuthController::class, 'verifyEmail'])->name('auth.verify')->middleware('signed');
    Route::post('forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});


