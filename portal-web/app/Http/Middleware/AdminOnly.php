<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated actor is an Admin, not a regular User.
 *
 * Route-level guard that blocks User tokens from reaching admin-scoped
 * endpoints even if the underlying auth guard accepted them.
 */
final class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof Admin) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden');
        }

        return $next($request);
    }
}