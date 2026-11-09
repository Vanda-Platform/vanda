<?php

declare(strict_types=1);

namespace Vanda\Support;

/**
 * The icon names the renderer draws (resources/icons.json, kept in step with the renderer's own
 * map by its icons.test.ts). Anything given to ->icon() must be one of them.
 *
 *     Icons::exists('trash');   // true
 *     Icons::closest('trashcan'); // 'trash'
 */
final class Icons
{
    /** @var list<string>|null */
    private static ?array $names = null;

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        if (self::$names === null) {
            $file = dirname(__DIR__, 2).'/resources/icons.json';
            $names = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            self::$names = is_array($names) ? array_values(array_filter($names, 'is_string')) : [];
        }

        return self::$names;
    }

    public static function exists(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /** The known name nearest to a misspelt one, if any is near enough to be meant. */
    public static function closest(string $name): ?string
    {
        return Suggest::closest($name, self::names());
    }
}
