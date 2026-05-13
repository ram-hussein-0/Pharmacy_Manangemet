<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/users', [UserController::class, 'index']);

// رابط لإرسال بيانات الموظف الجديد
Route::post('/users/store', [UserController::class, 'store']);

// رابط لحذف موظف
Route::delete('/users/delete/{id}', [UserController::class, 'destroy']);

// رابط لتسجيل الدخول 
Route::post('/login', [UserController::class, 'login']);
