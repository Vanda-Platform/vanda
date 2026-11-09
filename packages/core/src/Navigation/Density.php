<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Vanda\VandaException;

/**
 * How much of the panel fits on a screen. config('vanda.density') sets it for everyone; each user
 * may switch to the other one under the menu (remembered in their browser). Phones always get the
 * comfortable sizes: a finger needs them.
 *
 *     // config/vanda.php
 *     'density' => 'compact',          // or VANDA_DENSITY=compact in .env
 */
enum Density: string
{
    /** Fields and buttons 36px high, airy table rows, cards and menu. */
    case Comfortable = 'comfortable';

    /** Fields and buttons 32px high, table rows of about 36px, tighter cards and menu. */
    case Compact = 'compact';

    /** The panel's density from config('vanda.density'); not set means comfortable. */
    public static function configured(): self
    {
        $value = config('vanda.density') ?? self::Comfortable;

        if ($value instanceof self) {
            return $value;
        }

        return (is_string($value) ? self::tryFrom(strtolower(trim($value))) : null) ?? throw new VandaException(sprintf(
            "config('vanda.density') is %s. Write 'comfortable' or 'compact' in config/vanda.php (or VANDA_DENSITY in .env).",
            var_export($value, true),
        ));
    }
}
