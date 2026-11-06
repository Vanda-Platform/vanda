<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vanda\Activity\ActivityEvent;
use Vanda\Activity\ActivityLog;

final class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $guard = Auth::guard(config('vanda.guard'));
        if (($user = $guard->user()) !== null) {
            app(ActivityLog::class)->recordSession(ActivityEvent::Logout, $user);
        }

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vanda.login');
    }
}
