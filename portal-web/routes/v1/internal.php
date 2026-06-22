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
Route::prefix('internal')->middleware(['api.log', 'shared.token:internal'])->group(function (): void {
    Route::post('profile-publishes', [InternalProfilePublishController::class, 'store']);
    Route::get('query-logs', [QueryLogReadController::class, 'logs']);
    Route::get('query-analytics', [QueryLogReadController::class, 'analytics']);

    // 2026-06-22 fix: geodns health-view 改用 node.token 鉴权。
    // 历史设计：与 portal-web 共享 INTERNAL_SHARED_TOKEN，但 geodns install 时
    // 实际拿的是 node token（注册前还没有 api_key），两个 token 不一致导致 401。
    // 改用 node.token 中间件后，geodns 直接用 install 时的 node token 鉴权即可。
    Route::get('geodns/health-view', [HealthViewController::class, 'show'])
        ->middleware('node.token')
        ->withoutMiddleware('shared.token:internal');
});
