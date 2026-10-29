<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HabitCompletionController;
use App\Http\Controllers\HabitController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'show']);

    Route::apiResource('habits', HabitController::class);

    Route::put('/habits/{habit}/completions/today', [HabitCompletionController::class, 'store']);
    Route::delete('/habits/{habit}/completions/today', [HabitCompletionController::class, 'destroy']);
});
