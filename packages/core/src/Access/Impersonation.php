<?php

declare(strict_types=1);

namespace Vanda\Access;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Vanda\VandaException;

/**
 * Signing in as another user to see the panel through their eyes, as Orchid's
 * Impersonation does. The panel shows a banner with a way back:
 *
 *     #[Action(validate: false)]
 *     #[Can('platform.impersonate')]
 *     public function impersonate(): Result
 *     {
 *         Impersonation::loginAs($this->user);
 *
 *         return Result::redirectTo(Panel::home());
 *     }
 */
final class Impersonation
{
    private const KEY = 'vanda.impersonator';

    public static function loginAs(Authenticatable $user): void
    {
        $guard = self::guard();
        $current = $guard->user();

        if ($current === null) {
            throw new VandaException('Impersonation needs a signed-in user: there is nobody to return to.');
        }
        if ($current->getAuthIdentifier() === $user->getAuthIdentifier()) {
            throw new VandaException('A user cannot sign in as themselves. Hide the button with ->visible(! $this->user->is(auth()->user())).');
        }

        // Signing in as someone twice still returns to the first, real user.
        if (! session()->has(self::KEY)) {
            session()->put(self::KEY, $current->getAuthIdentifier());
        }

        $guard->login($user);
        session()->regenerate();
    }

    /**
     * Back to the user who started impersonating.
     */
    public static function stop(): void
    {
        $id = session()->pull(self::KEY);

        if ($id !== null) {
            self::guard()->loginUsingId($id);
            session()->regenerate();
        }
    }

    public static function isActive(): bool
    {
        return session()->has(self::KEY);
    }

    public static function impersonator(): ?Authenticatable
    {
        $id = session()->get(self::KEY);

        return $id === null ? null : self::guard()->getProvider()->retrieveById($id);
    }

    private static function guard(): StatefulGuard
    {
        $guard = Auth::guard(config('vanda.guard'));

        if (! $guard instanceof StatefulGuard) {
            throw new VandaException('Impersonation needs a session guard. Set vanda.guard to a guard with the "session" driver.');
        }

        return $guard;
    }
}
