<?php

declare(strict_types=1);

namespace Vanda\Support;

/**
 * Dot-path helpers shared by state, reactions and validation.
 */
final class Paths
{
    /**
     * "form" and "form.*" match "form.city"; "form.city" matches only itself.
     */
    public static function matches(string $pattern, string $path): bool
    {
        if ($pattern === $path || str_starts_with($path, $pattern.'.')) {
            return true;
        }

        return str_contains($pattern, '*') && fnmatch($pattern, $path);
    }

    /**
     * @param list<string> $patterns
     */
    public static function matchesAny(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Leaf paths whose values differ between two state arrays.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return list<string>
     */
    public static function diff(array $before, array $after, string $prefix = ''): array
    {
        $changed = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $a = $before[$key] ?? null;
            $b = $after[$key] ?? null;

            if (is_array($a) && is_array($b) && ! array_is_list($a) && ! array_is_list($b)) {
                array_push($changed, ...self::diff($a, $b, $path));
            } elseif ($a !== $b) {
                $changed[] = $path;
            }
        }

        return $changed;
    }

    /** "form.city" → "/state/form/city" (JSON Pointer). */
    public static function pointer(string $root, string $path): string
    {
        $segments = array_map(
            static fn (string $s) => str_replace(['~', '/'], ['~0', '~1'], $s),
            explode('.', $path),
        );

        return '/'.$root.'/'.implode('/', $segments);
    }
}
