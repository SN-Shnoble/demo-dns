<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated actor is a regular User, not an Admin.
 *
 * Route-level guard that blocks Admin tokens from reaching user-scoped
 * endpoints even if the underlying auth guard accepted them.
 */
final class UserOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden');
        }

        return $next($request);
    }
}