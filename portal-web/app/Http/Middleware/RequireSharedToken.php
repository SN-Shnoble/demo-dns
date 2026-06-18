<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireSharedToken
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $expected = match ($scope) {
            'bootstrap' => config('shared-tokens.bootstrap'),
            'internal' => config('shared-tokens.internal'),
            'admin' => config('shared-tokens.admin'),
            default => null,
        };

        $provided = $this->resolveProvidedToken($request, $scope);

        if ($expected === null || $expected === '' || ! hash_equals($expected, (string) $provided)) {
            return new JsonResponse([
                'message' => 'Unauthorized shared token.',
            ], 401);
        }

        return $next($request);
    }

    private function resolveProvidedToken(Request $request, string $scope): ?string
    {
        $authorization = trim((string) $request->header('Authorization', ''));

        if ($authorization !== '') {
            [$scheme, $token] = array_pad(preg_split('/\s+/', $authorization, 2) ?: [], 2, null);

            if ($token !== null && $this->matchesScheme((string) $scheme, $scope)) {
                return $token;
            }
        }

        return $request->header('X-Api-Token')
            ?? $request->header('X-Internal-Token')
            ?? $request->header('X-Admin-Token')
            ?? $request->bearerToken();
    }

    private function matchesScheme(string $scheme, string $scope): bool
    {
        $expected = match ($scope) {
            'bootstrap' => 'bootstrap',
            'internal' => 'internal',
            'admin' => 'admin',
            default => '',
        };

        return $expected !== '' && strcasecmp($scheme, $expected) === 0;
    }
}
