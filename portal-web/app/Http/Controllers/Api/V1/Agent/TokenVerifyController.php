<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Models\NodeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TokenVerifyController
{
    /**
     * POST /api/v1/agent/tokens/verify
     *
     * DNS Resolver 安装时用 --token 换取 api_key + secret。
     * 前端签发 token 时返回的 api_key 即为这里的 token，
     * 服务端通过 token_hash（sha256(token)）查找。
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:100',
        ]);

        $tokenHash = hash('sha256', $validated['token']);
        $token = NodeToken::query()
            ->where('token_hash', $tokenHash)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$token) {
            return response()->json(['error' => [
                'code' => 'invalid_token',
                'message' => 'Token not found or expired.',
            ]], 404);
        }

        $plainSecret = decrypt($token->hmac_secret_encrypted);

        return response()->json(['data' => [
            'node_id' => $token->node_id,
            'api_key' => $validated['token'],
            'secret' => $plainSecret,
        ]]);
    }
}
