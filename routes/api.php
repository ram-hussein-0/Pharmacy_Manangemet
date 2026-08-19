<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [UserController::class, 'logout']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/store', [UserController::class, 'store']);
    Route::delete('/users/delete/{id}', [UserController::class, 'destroy'])
        ->whereNumber('id');
});
