<?php

declare(strict_types=1);

namespace Vanda\Support;

use RuntimeException;

/**
 * HMAC signatures for snapshots and action tokens, keyed by APP_KEY.
 */
final class Signer
{
    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::key());
    }

    public static function verify(string $payload, string $signature): bool
    {
        return hash_equals(self::sign($payload), $signature);
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        if ($key === '') {
            throw new RuntimeException('APP_KEY is empty. Run `php artisan key:generate`: Vanda signs screen state with it.');
        }

        return $key;
    }
}
