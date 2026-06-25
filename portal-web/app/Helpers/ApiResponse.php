<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * API 统一响应工具。
 *
 * 统一格式：
 *   成功: { data: ..., meta?: ... }
 *   错误: { error: { code: string, message: string } }
 */
final class ApiResponse
{
    /**
     * 成功响应。
     *
     * @param  mixed       $data
     * @param  array|null  $meta
     * @param  int         $status
     * @return JsonResponse
     */
    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        return response()->json($payload, $status);
    }

    /**
     * 错误响应。
     *
     * @param  string  $code     错误码（如 VALIDATION_FAILED）
     * @param  string  $message  人类可读的错误消息
     * @param  int     $status   HTTP 状态码
     * @return JsonResponse
     */
    public static function error(string $code, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
