<?php

declare(strict_types=1);

namespace Vanda\Settings;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use TypeError;
use ValueError;

/**
 * Reads and writes the rows of vanda_settings. Bound per request (scoped), so the first
 * Settings::get() of a request reads every row with one query and the rest read memory;
 * Octane requests and queued jobs each start afresh.
 */
final class SettingsStore
{
    /** @var array<string, array<string, mixed>>|null key => saved values, secrets encrypted */
    private ?array $rows = null;

    /**
     * @param class-string<Settings> $class
     */
    public function get(string $class): Settings
    {
        $settings = $class::fromArray([]);
        $secrets = $class::secrets();

        foreach ($this->rows()[$class::key()] ?? [] as $name => $value) {
            if (in_array($name, $secrets, true)) {
                $value = self::decrypt($value);
                if ($value === null) {
                    continue;
                }
            }

            try {
                $settings->fill([$name => $value]);
            } catch (TypeError|ValueError) {
                // The property changed since the value was saved: its default holds.
            }
        }

        return $settings;
    }

    public function save(Settings $settings): void
    {
        $key = $settings::key();
        $record = StoredSettings::query()->firstOrNew(['name' => $key]);
        $saved = is_array($record->values) ? $record->values : [];
        $values = $settings->toArray();

        foreach ($settings::secrets() as $name) {
            $value = $values[$name] ?? null;
            $encrypted = $saved[$name] ?? null;

            if ($value === null || $value === '') {
                // Nothing typed: the saved key stays.
                if ($encrypted !== null) {
                    $values[$name] = $encrypted;
                } else {
                    unset($values[$name]);
                }

                continue;
            }

            // Encryption differs every time, so an unchanged secret keeps its old text and the log
            // does not show it as changed.
            $values[$name] = $encrypted !== null && self::decrypt($encrypted) === $value
                ? $encrypted
                : Crypt::encryptString((string) json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        $record->describe = ['secret' => $settings::secrets(), 'enums' => $settings::enums()];
        $record->values = $values;
        $record->save();

        if ($this->rows !== null) {
            $this->rows[$key] = $values;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        try {
            $found = StoredSettings::query()->get(['name', 'values']);
        } catch (QueryException $exception) {
            // Before `migrate` (a service provider reading settings while the table is being
            // created) the defaults hold; any other database error is not hidden.
            if (Schema::hasTable((new StoredSettings)->getTable())) {
                throw $exception;
            }

            return [];
        }

        $this->rows = [];
        foreach ($found as $row) {
            $this->rows[$row->name] = is_array($row->values) ? $row->values : [];
        }

        return $this->rows;
    }

    /** A value encrypted with another APP_KEY (or not encrypted) is no value. */
    private static function decrypt(mixed $encrypted): mixed
    {
        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($encrypted), true);
        } catch (DecryptException) {
            return null;
        }
    }
}
