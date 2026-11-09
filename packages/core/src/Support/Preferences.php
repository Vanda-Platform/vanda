<?php

declare(strict_types=1);

namespace Vanda\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * What a user chose in the panel and should find again, per account (table `vanda_preferences`):
 * a JSON value under a key. Tables keep their view here (Vanda\Screen\RememberedView).
 *
 *     Preferences::put($user, 'table:'.UserListScreen::class, ['filter' => ['status' => 'blocked']]);
 *     Preferences::get($user, 'table:'.UserListScreen::class); // ['filter' => ['status' => 'blocked']]
 */
final class Preferences
{
    public const TABLE = 'vanda_preferences';

    /**
     * @return array<string, mixed>|null null when nothing is saved under the key
     */
    public static function get(Authenticatable $user, string $key): ?array
    {
        $value = DB::table(self::TABLE)->where('user_id', self::userId($user))->where('key', self::key($key))->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Saves the value (an empty array too: «nothing chosen» is a choice). Writes only when it changed.
     *
     * @param array<string, mixed> $value
     */
    public static function put(Authenticatable $user, string $key, array $value): void
    {
        $json = json_encode($value === [] ? new \stdClass : $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $where = ['user_id' => self::userId($user), 'key' => self::key($key)];
        $current = DB::table(self::TABLE)->where($where)->value('value');

        // Compared decoded and regardless of key order: MySQL rewrites and reorders the JSON it stores.
        if (is_string($current) && json_decode($current, true) == $value) {
            return;
        }

        $now = now();
        $current === null
            ? DB::table(self::TABLE)->insert([...$where, 'value' => $json, 'created_at' => $now, 'updated_at' => $now])
            : DB::table(self::TABLE)->where($where)->update(['value' => $json, 'updated_at' => $now]);
    }

    public static function forget(Authenticatable $user, string $key): void
    {
        DB::table(self::TABLE)->where('user_id', self::userId($user))->where('key', self::key($key))->delete();
    }

    private static function userId(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }

    /** Keys fit the column: a long one (a screen with many route parameters) becomes its hash. */
    private static function key(string $key): string
    {
        return strlen($key) <= 191 ? $key : 'sha1:'.sha1($key);
    }
}
