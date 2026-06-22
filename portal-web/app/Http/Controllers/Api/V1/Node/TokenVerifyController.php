<?php

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\NodeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class TokenVerifyController
{
    /**
     * POST /api/v1/node/tokens/verify
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

        // 2026-06-22: 兼容 APP_KEY 轮换/历史密文 — 解密失败时 secret 留空，
        // 不再因历史密文不可解而 500。resolver 收到空 secret 仍能用 api_key (Bearer)
        // 完成 register 心跳,不影响节点上线。
        $plainSecret = '';
        try {
            $plainSecret = decrypt($token->hmac_secret_encrypted);
        } catch (Throwable) {
            // 历史密文与当前 APP_KEY 不匹配,secret 留空
        }

        return response()->json(['data' => [
            'node_id' => $token->node->node_code,
            'api_key' => $validated['token'],
            'secret' => $plainSecret,
        ]]);
    }
}
