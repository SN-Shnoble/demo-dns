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
        $node = $this->tokens->resolveNodeFromBearer($request->bearerToken());

        if ($node === null) {
            return new JsonResponse([
                'message' => 'Invalid or missing node token.',
            ], 401);
        }

        $request->attributes->set('node', $node);

        return $next($request);
    }
}
