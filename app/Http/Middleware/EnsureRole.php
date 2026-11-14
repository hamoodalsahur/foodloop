<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a signed-in user through only if their role is one of the listed ones.
 * Use it after `auth`:
 *
 *     Route::middleware(['auth', 'role:seller'])->group(...);
 *     Route::middleware(['auth', 'role:seller,admin'])->group(...);
 *
 * Anyone else gets a 403 "not allowed" page. (Guests never get here: `auth`
 * has already sent them to the login page.)
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role?->value;

        abort_unless(in_array($role, $roles, true), 403, 'This page is for '.implode(' or ', $roles).' accounts.');

        return $next($request);
    }
}
