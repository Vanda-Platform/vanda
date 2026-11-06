<?php

declare(strict_types=1);

namespace Vanda\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Vanda\Panel;

final class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard(config('vanda.guard'))->check() && $request->isMethod('GET')) {
            return redirect(Panel::home());
        }

        return $next($request);
    }
}
