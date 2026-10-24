<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side permission gate. Usage: ->middleware('permission:users').
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        foreach ($permissions as $permission) {
            abort_unless($user->hasPermission($permission), 403, 'You are not allowed to access this area.');
        }

        return $next($request);
    }
}
