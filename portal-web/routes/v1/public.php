<?php

use App\Http\Controllers\Api\V1\Public\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes - 无需认证
|--------------------------------------------------------------------------
*/
Route::prefix('public/auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
});

Route::post('admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1');
