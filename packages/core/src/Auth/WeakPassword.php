<?php

declare(strict_types=1);

namespace Vanda\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Warns about a password anyone could guess. The check runs at sign-in, the only moment the
 * password is known; the result stays in the session, bound to the password's hash, so the
 * warning goes away by itself once the password is changed.
 *
 * The red strip shows on production only (APP_ENV=production); config('vanda.weak_password_banner')
 * true or false turns it on or off everywhere.
 */
final class WeakPassword
{
    private const SESSION = 'vanda.weak_password';

    /** Passwords from the top of every leaked list, and the defaults of seeders and demos. */
    private const COMMON = [
        'password', 'passw0rd', 'password1', 'password123', 'qwerty', 'qwerty123', 'qwertyuiop', 'admin', 'admin123', 'administrator',
        'letmein', 'welcome', 'secret', 'changeme', 'root', 'test', 'test123', 'user', 'iloveyou', 'monkey', 'dragon',
        '123456', '1234567', '12345678', '123456789', '1234567890', '111111', '000000', '123123', '654321',
        'пароль', 'йцукен', 'йцукенгшщз', 'qwe123', '1q2w3e4r', '1qaz2wsx', 'zaq12wsx',
    ];

    /** Pieces that make a password guessable when it is not long: "Password2026!", "secret-pass". */
    private const COMMON_PARTS = ['passw', 'qwerty', 'admin', 'secret', 'welcome', 'letmein', 'iloveyou', 'пароль', 'йцукен', '12345', '11111', '00000', 'abc123', '1q2w', 'zaq1'];

    /**
     * Short (under 10 characters), common, contains the login, few different characters,
     * one kind of character under 16, or a common piece under 16.
     */
    public static function check(string $password, string $login = ''): bool
    {
        $lower = mb_strtolower($password);
        $name = mb_strtolower(explode('@', $login)[0]);
        $kinds = preg_match('/\p{Ll}/u', $password) + preg_match('/\p{Lu}/u', $password)
            + preg_match('/\d/', $password) + preg_match('/[^\p{L}\d]/u', $password);

        return mb_strlen($password) < 10
            || in_array($lower, self::COMMON, true)
            || (mb_strlen($name) >= 3 && str_contains($lower, $name))
            || count(array_unique(mb_str_split($password))) < 5
            || ($kinds < 2 && mb_strlen($password) < 16)
            || (mb_strlen($password) < 16 && array_filter(self::COMMON_PARTS, static fn (string $part) => str_contains($lower, $part)) !== []);
    }

    /**
     * After a successful sign-in: remembers whether this password is weak, for this session.
     */
    public static function remember(Request $request, Authenticatable $user, string $password): void
    {
        if (! $request->hasSession()) {
            return;
        }

        self::check($password, (string) ($user->email ?? ''))
            ? $request->session()->put(self::SESSION, self::fingerprint($user))
            : $request->session()->forget(self::SESSION);
    }

    /**
     * The strip is shown: enabled here and the user still has the weak password they signed in with.
     */
    public static function shouldWarn(?Authenticatable $user): bool
    {
        $setting = config('vanda.weak_password_banner');
        $enabled = $setting === null ? app()->isProduction() : (bool) $setting;

        if (! $enabled || $user === null || ! request()->hasSession()) {
            return false;
        }

        $stored = request()->session()->get(self::SESSION);

        return is_string($stored) && hash_equals($stored, self::fingerprint($user));
    }

    private static function fingerprint(Authenticatable $user): string
    {
        return hash('sha256', $user->getAuthIdentifier().'|'.$user->getAuthPassword());
    }
}
