<?php

use App\Http\Controllers\Api\V1\Agent\ConfigAckController;
use App\Http\Controllers\Api\V1\Agent\ConfigPullController;
use App\Http\Controllers\Api\V1\Agent\HeartbeatController;
use App\Http\Controllers\Api\V1\Agent\QueryLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent Routes - DNS Resolver 节点认证
|--------------------------------------------------------------------------
*/
Route::prefix('agent')->group(function (): void {
    // Token 验证（无中间件，安装时用 token 换取 api_key + secret）
    Route::post('tokens/verify', [App\Http\Controllers\Api\V1\Agent\TokenVerifyController::class, 'verify']);

    Route::post('nodes/heartbeat', [HeartbeatController::class, 'store'])->middleware(['node.hmac']);
    Route::get('resolver/config', [ConfigPullController::class, 'show'])->middleware(['node.hmac']);
    Route::post('resolver/config/ack', [ConfigAckController::class, 'store'])->middleware(['node.hmac']);
    Route::post('query-logs/batch', [QueryLogController::class, 'batch'])->middleware(['node.hmac']);
});
