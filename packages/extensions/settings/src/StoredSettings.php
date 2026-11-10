<?php

declare(strict_types=1);

namespace Vanda\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Vanda\Activity\ActivityOptions;
use Vanda\Activity\RecordsActivity;

/**
 * The row of one Settings class in vanda_settings. Read and written by SettingsStore; the
 * activity log records each value that changed by itself, secrets without their values.
 *
 * @property string $name
 * @property array<string, mixed> $values
 */
final class StoredSettings extends Model
{
    use RecordsActivity;

    protected $table = 'vanda_settings';

    protected $guarded = [];

    protected $casts = ['values' => 'array'];

    /**
     * @internal set by SettingsStore before it saves: what the log calls the row and which of its
     * values it records without values
     *
     * @var array{title?: string, secret?: list<string>, enums?: array<string, class-string<\BackedEnum>>}
     */
    public array $describe = [];

    public function activityOptions(): ActivityOptions
    {
        // The log also asks a row that is not loaded (for the name of the type), which has no name yet.
        $name = is_string($this->name) ? $this->name : null;
        $page = $name === null ? null : SettingsExtension::registered()->pageOf($name);

        $options = ActivityOptions::make((string) __('vanda-settings::settings.title'))
            ->title(fn (): string => $this->describe['title'] ?? ($page === null ? Str::headline((string) $name) : SettingsExtension::titleOf($page)))
            ->only('values')
            ->expand('values', $this->describe['enums'] ?? [])
            ->secret(...($this->describe['secret'] ?? []));

        return $page === null ? $options : $options->screen($page);
    }
}
