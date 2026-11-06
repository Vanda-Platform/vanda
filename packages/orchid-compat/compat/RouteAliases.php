<?php

declare(strict_types=1);

namespace Vanda\OrchidCompat;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Old names for new routes: with ['admin.' => 'platform.'], route('platform.ticket.edit', ['item' => 5])
 * builds the URL of the route named 'admin.ticket.edit' (also in redirect()->route()).
 *
 * Uses Laravel's resolver for names that do not exist, so routes keep one name each,
 * Route::has('platform.…') stays false and route:cache is not affected.
 */
final class RouteAliases
{
    /**
     * @param array<string, string> $aliases prefix of the real names => prefix of the old ones
     */
    public static function register(array $aliases): void
    {
        URL::resolveMissingNamedRoutesUsing(static function (string $name, mixed $parameters, bool $absolute) use ($aliases): ?string {
            $target = self::target($name, $aliases);

            return $target === null ? null : URL::route($target, $parameters, $absolute);
        });
    }

    /**
     * The real route name behind an old one, or null.
     *
     * @param array<string, string> $aliases
     */
    public static function target(string $name, array $aliases): ?string
    {
        foreach ($aliases as $from => $to) {
            if (str_starts_with($name, $to) && Route::has($real = $from.substr($name, strlen($to)))) {
                return $real;
            }
        }

        return null;
    }
}
