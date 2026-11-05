<?php

declare(strict_types=1);

namespace Vanda\Access;

/**
 * Helpers for permission maps stored as JSON: {"platform.users": 1, "platform.roles": 0}.
 * Orchid stores 1/0, "1"/"0" or true/false; all are understood.
 */
final class Permissions
{
    /**
     * true = granted, false = explicitly denied, null = not set.
     */
    public static function isOn(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Keeps only registered keys and normalises values to true/false (null drops the key).
     *
     * @param array<string, mixed> $map
     * @return array<string, bool>
     */
    public static function clean(array $map): array
    {
        $registry = app(PermissionRegistry::class);
        $clean = [];

        foreach ($map as $key => $value) {
            $on = self::isOn($value);
            if ($on !== null && $registry->has((string) $key)) {
                $clean[(string) $key] = $on;
            }
        }

        return $clean;
    }
}
