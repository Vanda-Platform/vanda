<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard(config('vanda.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vanda.login');
    }
}
