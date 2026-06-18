<?php

use App\Http\Controllers\Api\V1\Internal\HealthViewController;
use App\Http\Controllers\Api\V1\Internal\ProfilePublishController as InternalProfilePublishController;
use App\Http\Controllers\Api\V1\Internal\QueryLogReadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal Routes - 内部服务间调用
|--------------------------------------------------------------------------
*/
Route::prefix('internal')->middleware('shared.token:internal')->group(function (): void {
    Route::post('profile-publishes', [InternalProfilePublishController::class, 'store']);
    Route::get('geodns/health-view', [HealthViewController::class, 'show']);
    Route::get('query-logs', [QueryLogReadController::class, 'logs']);
    Route::get('query-analytics', [QueryLogReadController::class, 'analytics']);
});
