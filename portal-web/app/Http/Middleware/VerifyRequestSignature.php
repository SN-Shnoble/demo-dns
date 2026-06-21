<?php

namespace App\Http\Middleware;

use App\Domain\Auth\NodeTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify a node-agent request using only its Bearer token.
 *
 * 2026-06-22 改造：统一两个 Go 程序（geodns/dns-resolver）的注册机制，
 * 删除 HMAC 签名校验，仅使用 NodeToken (Bearer) 鉴权。
 *
 * Required headers:
 *   Authorization: Bearer <plain token>
 *
 * On success: sets `node` and `node_token` request attributes for downstream handlers.
 */
final class VerifyRequestSignature
{
    public function __construct(
        private readonly NodeTokenService $tokens,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return $this->reject('missing_bearer_token');
        }

        $resolved = $this->tokens->resolveByToken($bearer);
        if ($resolved === null) {
            return $this->reject('invalid_credentials');
        }

        $request->attributes->set('node', $resolved['node']);
        $request->attributes->set('node_token', $resolved['token']);

        return $next($request);
    }

    private function reject(string $reason): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'unauthorized',
                'message' => 'Token verification failed.',
                'reason' => $reason,
            ],
        ], 401);
    }
}
