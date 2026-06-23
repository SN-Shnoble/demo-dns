<?php

namespace App\Http\Middleware;

use App\Domain\Auth\NodeTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateNodeToken
{
    public function __construct(
        private readonly NodeTokenService $tokens = new NodeTokenService(),
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->tokens->resolveByToken((string) $request->bearerToken());

        if ($resolved === null) {
            return new JsonResponse([
                'message' => 'Invalid or missing node token.',
            ], 401);
        }

        $request->attributes->set('node', $resolved['node']);
        $request->attributes->set('node_token', $resolved['token']);
        $request->attributes->set('node_token_plain', (string) $request->bearerToken());

        return $next($request);
    }
}
