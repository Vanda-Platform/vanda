<?php

declare(strict_types=1);

namespace Vanda\Http;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guests go to the Vanda sign-in page; reactions and actions get 401.
 */
final class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('vanda.guard');

        if (Auth::guard($guard)->guest()) {
            return $request->header('X-Vanda') === 'true'
                ? new JsonResponse(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('vanda.login'));
        }

        Auth::shouldUse($guard);

        return $next($request);
    }
}
