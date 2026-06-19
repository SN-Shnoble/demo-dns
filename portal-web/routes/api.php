<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| v1 API routes - split into modular files under routes/v1/
|
*/

Route::prefix('v1')->group(function (): void {
    // Public routes (no auth required)
    require base_path('routes/v1/public.php');

    // User routes (user auth required)
    require base_path('routes/v1/user.php');

    // Admin routes (sanctum + permission required)
    require base_path('routes/v1/admin.php');

    // Agent routes (node HMAC auth)
    require base_path('routes/v1/agent.php');

    // Internal routes (shared token auth)
    require base_path('routes/v1/internal.php');
});
